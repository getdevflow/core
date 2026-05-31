<?php

declare(strict_types=1);

namespace App\Application\Console\Commands;

use App\Infrastructure\Persistence\Repository\ExtensionRepository;
use JsonException;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Codefy\Framework\Console\ConsoleCommand;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;

use function Codefy\Framework\Helpers\base_path;

class ExtensionCacheWarmCommand extends ConsoleCommand
{
    protected string $name = 'extension:cache:warm';

    protected string $description = 'Warms the extensions cache.';

    /**
     * @return int
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws JsonException
     */
    public function handle(): int
    {
        $repository = new ExtensionRepository(
            composerLockPath: base_path(path: 'composer.lock')
        );
        $repository->plugins();
        $repository->themes();

        $this->terminalRaw(string: '<comment>Extension cache warmed up.</comment>');

        // return value is important when using CI
        // to fail the build when the command fails
        // 0 = success, other values = fail
        return self::SUCCESS;
    }
}
