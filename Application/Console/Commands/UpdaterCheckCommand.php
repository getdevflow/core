<?php

declare(strict_types=1);

namespace App\Application\Console\Commands;

use App\Application\Devflow;
use App\Infrastructure\Services\Updater;
use Codefy\Framework\Console\ConsoleCommand;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Exception;

use function App\Shared\Helpers\updater_server_url;

final class UpdaterCheckCommand extends ConsoleCommand
{
    protected string $name = 'cms:update:check';

    protected string $description = 'Checks if new updates are available.';

    /**
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function handle(): int
    {
        $updater = new Updater();
        $updater->setCurrentVersion(Devflow::release());
        $updater->setUpdateUrl(updateUrl: updater_server_url() . '/update-check');

        if ($updater->checkUpdate() !== false) {
            if ($updater->newVersionAvailable()) {
                $this->terminalInfo(sprintf(
                    'Release %s is available',
                    $updater->latestVersion
                ));
            } else {
                $this->terminalInfo(sprintf(
                    'Devflow %s is at the latest release.',
                    Devflow::release()
                ));
            }
        }
        return self::SUCCESS;
    }
}
