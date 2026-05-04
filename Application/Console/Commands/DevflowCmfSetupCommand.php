<?php

declare(strict_types=1);

namespace App\Application\Console\Commands;

use App\Shared\Services\EnvWriter;
use Codefy\Framework\Application;
use Codefy\Framework\Console\ConsoleCommand;
use RuntimeException;

use function App\Shared\Helpers\generate_unique_key;

class DevflowCmfSetupCommand extends ConsoleCommand
{
    protected string $name = 'devflow:setup';

    protected string $description = 'Prepares Devflow environment keys and base configuration.';

    public function __construct(protected Application $codefy)
    {
        parent::__construct(codefy: $codefy);
    }

    public function handle(): int
    {
        $basePath = rtrim($this->detectBasePath(), $this->codefy::DS);
        $envPath = $basePath . '/.env';
        $examplePath = $basePath . '/.env.example';
        $encKeyPath = $basePath . '/.enc.key';

        if (! file_exists($envPath) && file_exists($examplePath)) {
            copy($examplePath, $envPath);
        }

        $env = new EnvWriter($envPath);

        $env->set('APP_BASE_PATH', $basePath);
        $env->set('APP_KEY', $this->generateSecret());
        $env->set('APP_SALT', $this->generateSecret());

        file_put_contents($encKeyPath, $this->generateSecret() . PHP_EOL, LOCK_EX);

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
