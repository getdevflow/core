<?php

declare(strict_types=1);

namespace App\Application\Console\Commands;

use App\Infrastructure\Persistence\Repository\ExtensionRepository;
use App\Infrastructure\Services\ExtensionService;
use Codefy\Framework\Console\ConsoleCommand;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
use JsonException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Symfony\Component\Process\Process;

use function App\Shared\Helpers\get_global_option;

abstract class ExtensionCommand extends ConsoleCommand
{
    protected function validatePackage(string $package): void
    {
        if ($package === '') {
            throw new RuntimeException('Missing package name.');
        }

        if (! preg_match('/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/', $package)) {
            throw new RuntimeException('Invalid package name.');
        }
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws Exception
     * @throws ReflectionException
     * @throws TypeException
     */
    protected function assertPackageExistsInRegistry(string $package, string $type): void
    {
        $repository = new ExtensionRepository(
            composerLockPath: $this->codefy->basePath() . $this->codefy::DS . 'composer.lock'
        );

        $extensions = $type === 'plugin'
            ? $repository->plugins()
            : $repository->themes();

        if (array_any($extensions, fn ($extension) => ($extension['name'] ?? '') === $package)) {
            return;
        }

        throw new RuntimeException('Package is not available in the Devflow extension registry.');
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
            throw new RuntimeException($message);
        }

        return self::SUCCESS;
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws InvalidArgumentException
     */
    protected function clearExtensionCache(): void
    {
        $repository = new ExtensionRepository(
            composerLockPath: $this->codefy->basePath() . '/composer.lock'
        );

        $repository->clearCache();
    }

    /**
     * @param string $package
     * @param string $type
     * @return void
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws UnresolvableQueryHandlerException
     */
    protected function assertExtensionCanBeRemoved(string $package, string $type): void
    {
        if ($this->isExtensionEnabledOnAnySubsite($package, $type)) {
            throw new RuntimeException(
                ucfirst($type) . ' cannot be removed because it is enabled on one or more sites.'
            );
        }

        if ($type === 'plugin' && $this->isPluginPackageActivatedOnAnySite($package)) {
            throw new RuntimeException(
                'Plugin cannot be removed because it is activated on one or more sites.'
            );
        }

        if ($type === 'theme' && $this->isThemePackageActivatedOnAnySite($package)) {
            throw new RuntimeException(
                'Theme cannot be removed because it is activated on one or more sites.'
            );
        }
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws TypeException
     * @throws InvalidArgumentException
     */
    protected function isExtensionEnabledOnAnySubsite(string $package, string $type): bool
    {
        $optionKey = $type === 'plugin'
            ? 'available_plugins'
            : 'available_themes';

        $enabled = get_global_option($optionKey, []);

        if (is_string($enabled)) {
            $enabled = json_decode($enabled, true) ?: [];
        }

        if (! is_array($enabled)) {
            return false;
        }

        return array_any(
            $enabled,
            fn ($class) => is_string($class) && (
                str_contains(strtolower($class), strtolower($this->getPackageSlug($package))) ||
                $this->classBelongsToExtensionPackage($class, $package, $type)
            )
        );
    }

    /**
     * @throws ReflectionException
     * @throws TypeException
     * @throws UnresolvableQueryHandlerException
     */
    protected function isPluginPackageActivatedOnAnySite(string $package): bool
    {
        $service = new ExtensionService();

        return array_any(
            $service->getActivePluginClassesAcrossSites(),
            fn ($class) => is_string($class) && (
                str_contains(strtolower($class), strtolower($this->getPackageSlug($package))) ||
                $this->classBelongsToExtensionPackage($class, $package, 'plugin')
            )
        );
    }

    /**
     * @throws ReflectionException
     * @throws TypeException
     * @throws UnresolvableQueryHandlerException
     */
    protected function isThemePackageActivatedOnAnySite(string $package): bool
    {
        $service = new ExtensionService();

        return array_any(
            $service->getActiveThemeClassesAcrossSites(),
            fn ($class) => is_string($class) && (
                str_contains(strtolower($class), strtolower($this->getPackageSlug($package))) ||
                $this->classBelongsToExtensionPackage($class, $package, 'theme')
            )
        );
    }

    protected function classBelongsToExtensionPackage(string $class, string $package, string $type): bool
    {
        $autoload = $this->codefy->basePath() . '/public/' .
                ($type === 'plugin' ? 'plugins' : 'themes') .
                '/' .
                $this->getPackageSlug($package) .
                '/vendor/autoload.php';

        if (is_file($autoload)) {
            require_once $autoload;
        }

        if (! class_exists($class)) {
            return false;
        }

        $reflection = new ReflectionClass($class);
        $classFile = $reflection->getFileName();

        if (! is_string($classFile)) {
            return false;
        }

        $packagePath = $this->getExtensionInstallPath($package, $type);
        $realClassFile = realpath($classFile);

        if ($packagePath === false || $realClassFile === false) {
            return false;
        }

        return str_starts_with($realClassFile, $packagePath);
    }

    protected function getPackageSlug(string $package): string
    {
        $parts = explode('/', $package);

        return end($parts) ?: $package;
    }

    protected function getExtensionInstallPath(string $package, string $type): string|false
    {
        $base = $type === 'plugin'
                ? $this->codefy->basePath() . '/public/plugins'
                : $this->codefy->basePath() . 'public/themes';

        return realpath($base . '/' . $this->getPackageSlug($package));
    }
}
