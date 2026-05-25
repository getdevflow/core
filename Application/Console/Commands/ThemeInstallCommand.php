<?php

declare(strict_types=1);

namespace App\Application\Console\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Throwable;

final class ThemeInstallCommand extends ExtensionCommand
{
    protected string $name = 'theme:install';

    protected string $description = 'Installs a Devflow theme.';

    protected function configure(): void
    {
        $this->addArgument('package', InputArgument::REQUIRED, 'Theme package name.');
    }

    public function handle(): int
    {
        $package = (string) $this->getArgument('package');

        try {
            $this->validatePackage($package);
            $this->assertPackageExistsInRegistry($package, 'theme');

            $this->terminalRaw("<info>Installing theme {$package}...</info>");

            $this->runComposer([
                'composer',
                'require',
                $package,
                '--no-interaction',
                '--prefer-dist',
                '--no-progress',
            ]);

            $this->clearExtensionCache();

            $this->terminalRaw('<info>Theme installed successfully.</info>');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->terminalRaw('<error>' . $e->getMessage() . '</error>');

            return self::FAILURE;
        }
    }
}
