<?php

declare(strict_types=1);

namespace App\Application\Console\Commands;

use App\Application\Devflow;
use App\Infrastructure\Services\Update\PersistentMaintenanceMode;
use App\Infrastructure\Services\Update\UpdateLock;
use App\Infrastructure\Services\UpdateSigningKey;
use App\Infrastructure\Services\Updater;
use Codefy\Framework\Console\ConsoleCommand;
use Closure;
use JsonException;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Exception;
use RuntimeException;
use SodiumException;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Process\Process;
use Throwable;

use function App\Shared\Helpers\dfdb;
use function App\Shared\Helpers\updater_server_url;
use function Codefy\Framework\Helpers\storage_path;
use function function_exists;
use function sprintf;

final class UpdaterCommand extends ConsoleCommand
{
    protected string $name = 'cms:update';

    protected function configure(): void
    {
        parent::configure();

        $this
                ->addArgument(
                    name: 'release',
                    mode: InputArgument::OPTIONAL,
                    description: 'The semver release to upgrade to.'
                )
                ->setDescription(description: 'Updates the system to the newest release.')
                ->setHelp(
                    help: <<<EOT
The <info>cms:update</info> command updates you to the current release or to a release passed as an argument.
<info>php codex cms:update 1.1.0</info>
EOT
                );
    }

    /**
     * @return int
     * @throws Exception
     * @throws ExceptionInterface
     * @throws InvalidArgumentException
     * @throws SodiumException
     * @throws JsonException
     */
    public function handle(): int
    {
        $updater = new Updater();
        $updater->setSigningPublicKey(UpdateSigningKey::get());
        $updater->setCurrentVersion(Devflow::release());
        $updater->setUpdateUrl(updater_server_url() . '/update-check');

        $maintenanceMode = new PersistentMaintenanceMode(
            storage_path('temp') . Devflow::$PHP::DS . '.devflow-update-maintenance.json'
        );
        $maintenanceEngaged = false;
        $updater->setMaintenanceModeHandler(
            static function (bool $enabled) use ($maintenanceMode, &$maintenanceEngaged): bool {
                if ($enabled) {
                    $maintenanceEngaged = $maintenanceMode->enter();

                    return $maintenanceEngaged;
                }

                /*
                 * A false callback before this process enters maintenance is
                 * recovery of an interrupted prior update. Otherwise keep the
                 * site in maintenance through Composer and migrations below.
                 */
                return $maintenanceEngaged ? true : $maintenanceMode->leave();
            }
        );
        $updater->setMigrationTransaction(
            static fn (Closure $migration): mixed => dfdb()->transactional(
                static fn (): mixed => $migration()
            )
        );

        $check = $updater->checkUpdate();

        if ($check === false) {
            $this->terminalError('Update server cannot be reached. Please try again later.');
            return self::FAILURE;
        }

        if (!$updater->newVersionAvailable()) {
            $this->terminalComment('No updates needed.');
            return self::SUCCESS;
        }

        $workflowLock = new UpdateLock(
            storage_path('temp') . Devflow::$PHP::DS . '.devflow-update-workflow.lock'
        );

        if (!$workflowLock->acquire()) {
            $this->terminalError('Another Devflow update workflow is already running.');

            return self::FAILURE;
        }

        try {
            $versions = $updater->getVersionsToUpdate();

            $this->terminalInfo(sprintf(
                'Applying updates: %s',
                implode(', ', $versions)
            ));

            $updater->onEachUpdateFinish(function (string $version, bool $simulate): void {
                $this->terminalComment(sprintf('Updated to %s', $version));
            });

            try {
                $result = $updater->update(
                    simulateInstall: false,
                    deleteDownload: true
                );
            } catch (Throwable $exception) {
                if ($maintenanceEngaged) {
                    $maintenanceMode->leave();
                }

                throw $exception;
            }

            if ($result !== true) {
                if ($maintenanceEngaged && !$maintenanceMode->leave()) {
                    $this->terminalError('Update rollback completed, but maintenance mode could not be restored.');
                }

                $this->terminalError(sprintf('Update failed with status: %s', (string) $result));
                return self::FAILURE;
            }

            $postUpdateSucceeded = true;
            $maintenanceRestored = true;

            try {
                $this->terminalInfo('Checking for composer updates . . . . . . . . . .');
                if ($this->runComposer(['composer', 'update']) !== self::SUCCESS) {
                    $postUpdateSucceeded = false;
                }

                if ($postUpdateSucceeded) {
                    $this->terminalInfo('Checking for migrations to run');
                    $postUpdateSucceeded = $this->call('migrate') === self::SUCCESS;
                }

                if ($postUpdateSucceeded) {
                    $this->terminalInfo('Checking for site migrations to run');
                    $postUpdateSucceeded = $this->call('site:migrate') === self::SUCCESS;
                }
            } finally {
                if ($maintenanceEngaged) {
                    $maintenanceRestored = $maintenanceMode->leave();
                }
            }

            if (!$maintenanceRestored) {
                $this->terminalError('Updates ran, but maintenance mode could not be restored.');
                $postUpdateSucceeded = false;
            }

            if (!$postUpdateSucceeded) {
                $this->terminalError('Update post-processing did not complete successfully.');

                return self::FAILURE;
            }

            $this->terminalComment('Updates complete!');

            return self::SUCCESS;
        } finally {
            $workflowLock->release();
        }
    }

    protected function runComposer(array $command): int
    {
        if (! function_exists('proc_open')) {
            throw new RuntimeException('The function proc_open() must be enabled to execute commands.');
        }

        $process = new Process(
            command: $command,
            cwd: $this->codefy->basePath(),
            timeout: 300
        );

        $process->run();

        if (! $process->isSuccessful()) {
            $message = $process->getErrorOutput() ?: $process->getOutput();
            $this->terminalError($message);
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
