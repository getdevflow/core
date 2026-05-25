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
use ZipArchive;

use function App\Shared\Helpers\remote_file_exists;
use function App\Shared\Helpers\updater_server_url;
use function Codefy\Framework\Helpers\base_path;
use function curl_close;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt;
use function fclose;
use function fopen;
use function function_exists;
use function sprintf;
use function unlink;
use function version_compare;

use const CURLINFO_HTTP_CODE;
use const CURLOPT_FILE;
use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_NOBODY;
use const CURLOPT_URL;

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
     */
    protected function getCurrentRelease(): string
    {
        $updater = new Updater();
        $updater->setCurrentVersion(Devflow::release());
        $updater->setUpdateUrl(updateUrl: updater_server_url() . '/update-check');

        if ($updater->checkUpdate() !== false) {
            if ($updater->newVersionAvailable()) {
                return $updater->latestVersion;
            }
        }

        return Devflow::release();
    }

    protected function checkExternalFile($url): mixed
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // this will follow redirects
        curl_exec($ch);
        $retCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $retCode;
    }

    protected function getDownload($release, $url): void
    {
        $fh = fopen($release, 'w');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FILE, $fh);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // this will follow redirects
        curl_exec($ch);
        curl_close($ch);
        fclose($fh);
    }

    /**
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function handle(): int
    {
        $release = $this->getArgument(key: 'release');

        if ($release === null || $release === '') {
            $releaseValue = $this->getCurrentRelease();
        } else {
            $releaseValue = $release;
        }

        $zip = new ZipArchive();
        $file = sprintf(updater_server_url() . '/release/%s.zip', $releaseValue);

        if (version_compare(Devflow::release(), $releaseValue, '<')) {
            $remoteUpdateCheck = remote_file_exists(updater_server_url() . '/update-check/update.json');
            $zipFile = sprintf('%s.zip', $releaseValue);
            if ($remoteUpdateCheck && $this->checkExternalFile($file) === 200) {
                //Download file to the server
                $this->terminalInfo('Downloading . . . . . . . . . . . . .');
                $this->getDownload($zipFile, $file);

                //Unzip the file to update
                $this->terminalInfo('Unzipping . . . . . . . . . . . . .');
                $x = $zip->open($zipFile);

                if ($x === true) {
                    //Extract file in root.
                    $zip->extractTo(base_path());
                    $zip->close();

                    //Remove download after completion.
                    unlink($zipFile);
                }
                // Check for composer updates
                $this->terminalInfo('Checking for composer updates . . . . . . . . . .');
                $this->runComposer([
                    'composer',
                    'update',
                ]);

                $this->terminalInfo('Checking for migrations to run');
                $this->call('migrate');

                $this->terminalInfo('Checking for site migrations to run');
                $this->call('site:migrate');

                // Updates complete
                $this->terminalComment('Updates complete!');
            } elseif ($remoteUpdateCheck && $this->checkExternalFile($file) !== 200) {
                // Check for composer updates
                $this->terminalInfo('Checking for composer updates . . . . . . . . . .');
                $this->runComposer([
                    'composer',
                    'update',
                ]);

                // Updates complete
                $this->terminalComment('Updates complete!');
            } else {
                $this->terminalError('Update server cannot be reached. Please try again later.');
            }
        } else {
            $this->terminalComment('No updates needed.');
        }

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
