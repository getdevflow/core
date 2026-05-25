<?php

declare(strict_types=1);

namespace App\Application\Console\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Throwable;

final class PluginInstallCommand extends ExtensionCommand
{
    protected string $name = 'plugin:install';

    protected string $description = 'Installs a Devflow plugin.';

    protected function configure(): void
    {
        $this->addArgument('package', InputArgument::REQUIRED, 'Plugin package name.');
    }

    public function handle(): int
    {
        $package = (string) $this->getArgument('package');

        try {
            $this->validatePackage($package);
            $this->assertPackageExistsInRegistry($package, 'plugin');

            $this->terminalRaw("<info>Installing plugin {$package}...</info>");

            $this->runComposer([
                'composer',
                'require',
                $package,
                '--no-interaction',
                '--prefer-dist',
                '--no-progress',
            ]);

            $this->clearExtensionCache();

            $this->terminalRaw('<info>Plugin installed successfully.</info>');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->terminalRaw('<error>' . $e->getMessage() . '</error>');

            return self::FAILURE;
        }
    }
}
