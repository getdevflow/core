<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Devflow;
use App\Infrastructure\Persistence\Repository\ExtensionRepository;
use Exception;
use JsonException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Http\Factories\JsonResponseFactory;
use ReflectionException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\current_user_can;
use function array_any;
use function Codefy\Framework\Helpers\base_path;
use function Codefy\Framework\Helpers\trans;
use function Codefy\Framework\Helpers\view;
use function in_array;
use function preg_match;
use function Qubus\Routing\Helpers\redirect;
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
            Devflow::$PHP->flash->error(message: trans('Access denied.'));

            return redirect(admin_url('plugin'));
        }

        $repository = new ExtensionRepository(
            composerLockPath: base_path('composer.lock')
        );

        return view('framework::backend/admin/plugin/install', [
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
            Devflow::$PHP->flash->error(message: trans('Access denied.'));

            return redirect(admin_url('theme'));
        }

        $repository = new ExtensionRepository(
            composerLockPath: base_path('composer.lock')
        );

        return view('framework::backend/admin/theme/install', [
            'themes' => $repository->themes(),
        ]);
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws Exception
     */
    public function install(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $data = $request->getParsedBody();

            $package = trim((string) ($data['package'] ?? ''));
            $type = trim((string) ($data['type'] ?? ''));

            if ($package === '') {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Missing package name.',
                ], 422);
            }

            if (! in_array($type, ['plugin', 'theme'], true)) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Invalid extension type.',
                ], 422);
            }

            if (! preg_match('/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/', $package)) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Invalid Composer package name.',
                ], 422);
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
                'message' => ucfirst($type) . ' installed successfully.',
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
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws InvalidArgumentException
     * @throws JsonException
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

        if (array_any($extensions, fn($extension) => ($extension['name'] ?? '')===$package)) {
            return;
        }

        throw new RuntimeException('Package is not available in the Devflow extension registry.');
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
}
