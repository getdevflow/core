<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Devflow;
use App\Infrastructure\Persistence\Repository\ExtensionRepository;
use App\Infrastructure\Services\ExtensionService;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
use Exception;
use JsonException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Http\Factories\JsonResponseFactory;
use Qubus\Http\ServerRequest;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\current_user_can;
use function App\Shared\Helpers\get_global_option;
use function array_any;
use function class_exists;
use function Codefy\Framework\Helpers\base_path;
use function Codefy\Framework\Helpers\trans_html;
use function Codefy\Framework\Helpers\view;
use function function_exists;
use function in_array;
use function is_array;
use function is_file;
use function json_decode;
use function preg_match;
use function Qubus\Routing\Helpers\redirect;
use function strtolower;
use function trim;

final class ExtensionInstallerController
{
    /**
     * @return ResponseInterface
     * @throws JsonException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     * @throws TypeException
     * @throws ReflectionException
     * @throws Exception
     */
    public function plugins(): ResponseInterface
    {
        if (!current_user_can(perm: 'install:plugins')) {
            Devflow::$PHP->flash->error(message: trans_html('Access denied.'));

            return redirect(admin_url('plugin'));
        }

        $repository = new ExtensionRepository(
            composerLockPath: base_path('composer.lock')
        );

        return view('framework::backend/admin/plugin/install', [
            'title' => trans_html('Install Plugins'),
            'plugins' => $repository->plugins(),
        ]);
    }

    /**
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws Exception
     */
    public function themes(): ResponseInterface
    {
        if (!current_user_can(perm: 'install:themes')) {
            Devflow::$PHP->flash->error(message: trans_html('Access denied.'));

            return redirect(admin_url('theme'));
        }

        $repository = new ExtensionRepository(
            composerLockPath: base_path('composer.lock')
        );

        return view('framework::backend/admin/theme/install', [
            'title' => trans_html('Install Themes'),
            'themes' => $repository->themes(),
        ]);
    }

    /**
     * @param ServerRequest $request
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function install(ServerRequest $request): ResponseInterface
    {
        if (!current_user_can(perm: 'install:extensions')) {
            return $this->jsonResponse([
                'success' => false,
                'message' => trans_html('Access denied.'),
            ], 403);
        }

        try {
            $data = $request->getParsedBody();

            $package = trim((string) ($data['package'] ?? ''));
            $type = trim((string) ($data['type'] ?? ''));

            if ($package === '') {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => trans_html('Missing package name.'),
                ], 422);
            }

            if (! in_array($type, ['plugin', 'theme'], true)) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => trans_html('Invalid extension type.'),
                ], 422);
            }

            if (! preg_match('/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/', $package)) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => trans_html('Invalid Composer package name.'),
                ], 422);
            }

            if (! function_exists('proc_open')) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => trans_html(
                        'You need to enable proc_open() to install an extension.'
                    ),
                ], 500);
            }

            $this->assertPackageExistsInRegistry($package, $type);

            $process = new Process(
                command: [
                    'composer',
                    'require',
                    $package,
                    '--no-interaction',
                    '--prefer-dist',
                    '--no-progress',
                ],
                cwd: base_path(),
                timeout: 300
            );

            $process->run();

            if (! $process->isSuccessful()) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => $process->getErrorOutput() ?: $process->getOutput(),
                ], 500);
            }

            $repository = new ExtensionRepository(
                composerLockPath: base_path('composer.lock')
            );

            $repository->clearCache();

            return $this->jsonResponse([
                'success' => true,
                'message' => ucfirst($type) . trans_html(' installed successfully.'),
                'package' => $package,
                'type' => $type,
            ]);
        } catch (Throwable $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @param ServerRequest $request
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function remove(ServerRequest $request): ResponseInterface
    {
        if (!current_user_can(perm: 'uninstall:extensions')) {
            return $this->jsonResponse([
                'success' => false,
                'message' => trans_html('Access denied.'),
            ], 403);
        }

        try {
            $data = $request->getParsedBody();

            $package = trim((string) ($data['package'] ?? ''));
            $type = trim((string) ($data['type'] ?? ''));

            if ($package === '') {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => trans_html('Missing package name.'),
                ], 422);
            }

            if (! in_array($type, ['plugin', 'theme'], true)) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => trans_html('Invalid extension type.'),
                ], 422);
            }

            if (! preg_match('/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/', $package)) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => trans_html('Invalid Composer package name.'),
                ], 422);
            }

            if (! function_exists('proc_open')) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => trans_html(
                        'You need to enable proc_open() to uninstall an extension.'
                    ),
                ], 500);
            }

            $this->assertExtensionCanBeRemoved($package, $type);

            $process = new Process(
                command: [
                    'composer',
                    'remove',
                    $package,
                    '--no-interaction',
                    '--no-progress',
                ],
                cwd: base_path(),
                timeout: 300
            );

            $process->run();

            if (! $process->isSuccessful()) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => $process->getErrorOutput() ?: $process->getOutput(),
                ], 500);
            }

            $repository = new ExtensionRepository(
                composerLockPath: base_path('composer.lock')
            );

            $repository->clearCache();

            return $this->jsonResponse([
                'success' => true,
                'message' => ucfirst($type) . trans_html(' removed successfully.'),
                'package' => $package,
                'type' => $type,
            ]);
        } catch (Throwable $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @param string $package
     * @param string $type
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     */
    private function assertPackageExistsInRegistry(string $package, string $type): void
    {
        $repository = new ExtensionRepository(
            composerLockPath: base_path('composer.lock')
        );

        $extensions = match ($type) {
            'plugin' => $repository->plugins(),
            'theme' => $repository->themes(),
            default => [],
        };

        if (array_any($extensions, fn($extension) => ($extension['name'] ?? '') === $package)) {
            return;
        }

        throw new RuntimeException(trans_html('Extension not available in the Devflow extension registry.'));
    }

    /**
     * @throws Exception
     */
    private function jsonResponse(array $data, int $status = 200): ResponseInterface
    {
        return JsonResponseFactory::create(
            data: $data,
            status: $status
        );
    }

    /**
     * @param string $package
     * @param string $type
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws UnresolvableQueryHandlerException
     * @throws \Qubus\Exception\Exception
     */
    private function assertExtensionCanBeRemoved(string $package, string $type): void
    {
        if ($this->isExtensionEnabledOnAnySubsite($package, $type)) {
            throw new RuntimeException(
                ucfirst($type) . trans_html(' cannot be removed because it is enabled on one or more sites.')
            );
        }

        if ($type === 'plugin' && $this->isPluginPackageActivatedOnAnySite($package)) {
            throw new RuntimeException(
                trans_html('Plugin cannot be removed because it is activated on one or more sites.')
            );
        }

        if ($type === 'theme' && $this->isThemePackageActivatedOnAnySite($package)) {
            throw new RuntimeException(
                trans_html('Theme cannot be removed because it is activated on one or more sites.')
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
    private function isExtensionEnabledOnAnySubsite(string $package, string $type): bool
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

        $slug = strtolower($this->getPackageSlug($package));

        return array_any(
            $enabled,
            fn($class) => is_string($class) && (
                str_contains(strtolower($class), $slug) ||
                $this->classBelongsToExtensionPackage($class, $package, $type)
            )
        );
    }

    /**
     * @param string $package
     * @return bool
     * @throws ReflectionException
     * @throws TypeException
     * @throws UnresolvableQueryHandlerException
     */
    private function isPluginPackageActivatedOnAnySite(string $package): bool
    {
        $slug = $this->getPackageSlug($package);
        $service = new ExtensionService();

        return array_any(
            $service->getActivePluginClassesAcrossSites(),
            fn($class) => str_contains(strtolower($class), strtolower($slug)) ||
                    $this->classBelongsToExtensionPackage($class, $package, 'plugin')
        );
    }

    /**
     * @throws ReflectionException
     * @throws TypeException
     * @throws UnresolvableQueryHandlerException
     */
    private function isThemePackageActivatedOnAnySite(string $package): bool
    {
        $slug = $this->getPackageSlug($package);
        $service = new ExtensionService();

        return array_any(
            $service->getActiveThemeClassesAcrossSites(),
            fn($class) => str_contains(strtolower($class), strtolower($slug)) ||
                    $this->classBelongsToExtensionPackage($class, $package, 'theme')
        );
    }

    private function classBelongsToExtensionPackage(string $class, string $package, string $type): bool
    {
        $autoload = base_path('public/' . ($type === 'plugin' ? 'plugins' : 'themes')
                . '/' . $this->getPackageSlug($package) . '/vendor/autoload.php');

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

        if ($packagePath === false) {
            return false;
        }

        $realClassFile = realpath($classFile);

        if ($realClassFile === false) {
            return false;
        }

        return str_starts_with($realClassFile, $packagePath);
    }

    private function getPackageSlug(string $package): string
    {
        $parts = explode('/', $package);

        return end($parts) ?: $package;
    }

    private function getExtensionInstallPath(string $package, string $type): string|false
    {
        $slug = $this->getPackageSlug($package);

        $base = $type === 'plugin'
            ? base_path('public/plugins')
            : base_path('public/themes');

        return realpath($base . '/' . $slug);
    }
}
