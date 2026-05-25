<?php

declare(strict_types=1);

namespace App\Application\Console\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Throwable;

final class PluginRemoveCommand extends ExtensionCommand
{
    protected string $name = 'plugin:remove';

    protected string $description = 'Removes a Devflow plugin.';

    protected function configure(): void
    {
        $this->addArgument('package', InputArgument::REQUIRED, 'Plugin package name.');
    }

    public function handle(): int
    {
        $package = (string) $this->getArgument('package');

        try {
            $this->validatePackage($package);
            $this->assertExtensionCanBeRemoved($package, 'plugin');

            $this->terminalRaw("<comment>Removing plugin {$package}...</comment>");

            $this->runComposer([
                'composer',
                'remove',
                $package,
                '--no-interaction',
                '--no-progress',
            ]);

            $this->clearExtensionCache();

            $this->terminalRaw('<info>Plugin removed successfully.</info>');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->terminalRaw('<error>' . $e->getMessage() . '</error>');

            return self::FAILURE;
        }
    }
}
