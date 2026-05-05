<?php

declare(strict_types=1);

namespace App\Application\Console\Commands;

use Codefy\Framework\Application;
use Codefy\Framework\Console\ConsoleCommand;
use Symfony\Component\Console\Exception\ExceptionInterface;

use function file_exists;

class DevflowCmfInstallCommand extends ConsoleCommand
{
    protected string $name = 'devflow:install';

    protected string $description = 'Sets up, migrates, and installs Devflow CMF.';

    public function __construct(protected Application $codefy)
    {
        parent::__construct(codefy: $codefy);
    }

    /**
     * @throws ExceptionInterface
     */
    public function handle(): int
    {
        if(file_exists($this->codefy->storagePath() . '/install.lock')) {
            $this->terminalRaw(string: '<comment>Your system is already installed.</comment>');
            return self::SUCCESS;
        }

        $this->terminalRaw(string: '<info>Starting Devflow installer...</info>');

        if ($this->call('devflow:setup') !== self::SUCCESS) {
            return self::FAILURE;
        }

        if ($this->call('migrate') !== self::SUCCESS) {
            return self::FAILURE;
        }

        if ($this->call('cms:install') !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->terminalRaw(string: '<info>Devflow CMF installed successfully.</info>');

        return self::SUCCESS;
    }
}
