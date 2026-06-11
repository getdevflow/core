<?php

declare(strict_types=1);

namespace App\Application\Console\Commands;

use App\Application\Devflow;
use App\Infrastructure\Services\Updater;
use Codefy\Framework\Console\ConsoleCommand;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Exception;
use RuntimeException;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Process\Process;

use function App\Shared\Helpers\updater_server_url;
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
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function handle(): int
    {
        $updater = new Updater();
        $updater->setCurrentVersion(Devflow::release());
        $updater->setUpdateUrl(updater_server_url() . '/update-check');

        $check = $updater->checkUpdate();

        if ($check === false) {
            $this->terminalError('Update server cannot be reached. Please try again later.');
            return self::FAILURE;
        }

        if (!$updater->newVersionAvailable()) {
            $this->terminalComment('No updates needed.');
            return self::SUCCESS;
        }

        $versions = $updater->getVersionsToUpdate();

        $this->terminalInfo(sprintf(
            'Applying updates: %s',
            implode(', ', $versions)
        ));

        $updater->onEachUpdateFinish(function (string $version, bool $simulate): void {
            $this->terminalComment(sprintf('Updated to %s', $version));
        });

        $result = $updater->update(
            simulateInstall: false,
            deleteDownload: true
        );

        if ($result !== true) {
            $this->terminalError(sprintf('Update failed with status: %s', (string) $result));
            return self::FAILURE;
        }

        $this->terminalInfo('Checking for composer updates . . . . . . . . . .');
        if ($this->runComposer(['composer', 'update']) !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->terminalInfo('Checking for migrations to run');
        $this->call('migrate');

        $this->terminalInfo('Checking for site migrations to run');
        $this->call('site:migrate');

        $this->terminalComment('Updates complete!');

        return self::SUCCESS;
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
