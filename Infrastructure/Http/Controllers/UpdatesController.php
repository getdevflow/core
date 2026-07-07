<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Devflow;
use App\Infrastructure\Services\UpdateManager;
use Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Http\ServerRequest;
use ReflectionException;
use Throwable;

use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\current_user_can;
use function App\Shared\Helpers\is_main_site;
use function Codefy\Framework\Helpers\trans_html;
use function Codefy\Framework\Helpers\view;
use function Qubus\Routing\Helpers\redirect;

final readonly class UpdatesController
{
    public function __construct(private UpdateManager $updates)
    {
    }

    /**
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     * @throws TypeException
     * @throws ReflectionException
     * @throws Exception
     */
    public function index(): ResponseInterface
    {
        if (false === current_user_can(perm: 'core:updates')) {
            Devflow::$PHP->flash->error(
                message: trans_html('Access denied.')
            );
            return redirect(admin_url());
        }

        if (!is_main_site()) {
            Devflow::$PHP->flash->error(
                message: trans_html('Access denied.')
            );

            return redirect(admin_url());
        }

        return view('framework::backend/updates', [
            'title' => trans_html('Updates'),
            'updates' => $this->updates->overview(),
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
     */
    public function updatePlugin(ServerRequest $request): ResponseInterface
    {
        if (false === current_user_can(perm: 'core:updates')) {
            Devflow::$PHP->flash->error(
                message: trans_html('Access denied.')
            );
            return redirect(admin_url());
        }

        return $this->updatePackage($request, 'devflow-plugin');
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
     */
    public function updateTheme(ServerRequest $request): ResponseInterface
    {
        if (false === current_user_can(perm: 'core:updates')) {
            Devflow::$PHP->flash->error(
                message: trans_html('Access denied.')
            );
            return redirect(admin_url());
        }

        return $this->updatePackage($request, 'devflow-theme');
    }

    /**
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     */
    public function updateAllPlugins(): ResponseInterface
    {
        if (false === current_user_can(perm: 'core:updates')) {
            Devflow::$PHP->flash->error(
                message: trans_html('Access denied.')
            );
            return redirect(admin_url());
        }

        return $this->updateAll('devflow-plugin');
    }

    /**
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     */
    public function updateAllThemes(): ResponseInterface
    {
        if (false === current_user_can(perm: 'core:updates')) {
            Devflow::$PHP->flash->error(
                message: trans_html('Access denied.')
            );
            return redirect(admin_url());
        }

        return $this->updateAll('devflow-theme');
    }

    private function updatePackage(ServerRequest $request, string $type): ResponseInterface
    {
        $data = (array) $request->getParsedBody();
        $package = (string) ($data['package'] ?? '');

        try {
            $result = $this->updates->updatePackage($package, $type);

            if ($result['success']) {
                Devflow::$PHP->flash->success(trans_html('Package updated successfully.'));
            } else {
                Devflow::$PHP->flash->error($result['output']);
            }
        } catch (Throwable $e) {
            Devflow::$PHP->flash->error($e->getMessage());
        }

        return redirect('/admin/updates/');
    }

    private function updateAll(string $type): ResponseInterface
    {
        try {
            $result = $this->updates->updateAll($type);

            if ($result['success']) {
                Devflow::$PHP->flash->success(trans_html('Updates completed successfully.'));
            } else {
                Devflow::$PHP->flash->error($result['output']);
            }
        } catch (Throwable $e) {
            Devflow::$PHP->flash->error($e->getMessage());
        }

        return redirect('/admin/updates/');
    }
}
