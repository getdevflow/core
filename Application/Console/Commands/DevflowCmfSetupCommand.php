<?php

declare(strict_types=1);

namespace App\Application\Console\Commands;

use App\Shared\Services\EnvWriter;
use Codefy\Framework\Console\ConsoleCommand;
use RuntimeException;

use Symfony\Component\Console\Exception\ExceptionInterface;

use function App\Shared\Helpers\generate_unique_key;
use function file_exists;

class DevflowCmfSetupCommand extends ConsoleCommand
{
    protected string $name = 'devflow:setup';

    protected string $description = 'Prepares Devflow environment keys and base configuration.';

    /**
     * @throws ExceptionInterface
     */
    public function handle(): int
    {
        if(file_exists($this->codefy->storagePath() . '/install.lock')) {
            $this->terminalRaw(string: '<comment>Your system is already installed.</comment>');
            return self::SUCCESS;
        }

        $basePath = rtrim($this->codefy->basePath(), $this->codefy::DS);
        $envPath = $basePath . '/.env';
        $examplePath = $basePath . '/.env.example';

        if (! file_exists($envPath) && file_exists($examplePath)) {
            copy($examplePath, $envPath);
        }

        $env = new EnvWriter($envPath);

        $env->set('APP_KEY', $this->generateSecret());
        $env->set('APP_SALT', $this->generateSecret());

        if ($this->call('generate:key:file') !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->terminalRaw(string: '<info>Devflow environment setup completed.</info>');

        return self::SUCCESS;
    }

    private function generateSecret(): string
    {
        return generate_unique_key(length: 32);
    }

    private function detectBasePath(): string
    {
        $dir = getcwd();

        while ($dir !== false && $dir !== dirname($dir)) {
            if (file_exists($dir . '/composer.json')) {
                return $dir;
            }

            $dir = dirname($dir);
        }

        throw new RuntimeException('Could not determine project base path (composer.json not found).');
    }
}
