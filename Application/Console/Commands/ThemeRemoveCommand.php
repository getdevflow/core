<?php

declare(strict_types=1);

namespace App\Application\Console\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Throwable;

final class ThemeRemoveCommand extends ExtensionCommand
{
    protected string $name = 'theme:remove';

    protected string $description = 'Removes a Devflow theme package.';

    protected function configure(): void
    {
        $this->addArgument('package', InputArgument::REQUIRED, 'Theme package name.');
    }

    public function handle(): int
    {
        $package = (string) $this->getArgument('package');

        try {
            $this->validatePackage($package);
            $this->assertExtensionCanBeRemoved($package, 'theme');

            $this->terminalRaw("<comment>Removing theme {$package}...</comment>");

            $this->runComposer([
                'composer',
                'remove',
                $package,
                '--no-interaction',
                '--no-progress',
            ]);

            $this->clearExtensionCache();

            $this->terminalRaw('<info>Theme removed successfully.</info>');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->terminalRaw('<error>' . $e->getMessage() . '</error>');

            return self::FAILURE;
        }
    }
}
