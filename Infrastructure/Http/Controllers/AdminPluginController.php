<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Devflow;
use Codefy\CommandBus\Exceptions\CommandPropertyNotFoundException;
use Codefy\Framework\Http\BaseController;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Http\ServerRequest;
use Qubus\Http\Session\SessionException;
use ReflectionException;

use function App\Shared\Helpers\activate_plugin;
use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\current_user_can;
use function App\Shared\Helpers\deactivate_plugin;
use function Codefy\Framework\Helpers\logger;
use function Codefy\Framework\Helpers\view;
use function Qubus\Security\Helpers\t__;

final class AdminPluginController extends BaseController
{
    /**
     * @return ResponseInterface|string
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws SessionException
     * @throws TypeException
     * @throws \Exception
     */
    public function plugins(): ResponseInterface|string
    {
        if (false === current_user_can(perm: 'manage:plugins')) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Access denied.', domain: 'devflow')
            );

            return $this->redirect(admin_url());
        }

        return view(
            template: 'framework::backend/admin/plugin/index',
            data: ['title' => t__(msgid: 'Plugins', domain: 'devflow')]
        );
    }

    /**
     * @param ServerRequest $request
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws CommandPropertyNotFoundException
     * @throws UnresolvableQueryHandlerException
     */
    public function activate(ServerRequest $request): ResponseInterface
    {
        if (false === current_user_can(perm: 'activate:plugins')) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Access denied.', domain: 'devflow')
            );
            return $this->redirect(admin_url());
        }

        try {
            activate_plugin($request->getQueryParams()['id']);

            Devflow::$PHP->flash->success(t__(msgid: 'Plugin activated.', domain: 'devflow'));
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface | ReflectionException $e) {
            logger('error', $e->getMessage());
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Plugin activation exception occurred and was logged.', domain: 'devflow')
            );
        }

        return $this->redirect($request->getServerParams()['HTTP_REFERER']);
    }

    /**
     * @param ServerRequest $request
     * @return ResponseInterface
     * @throws CommandPropertyNotFoundException
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws UnresolvableQueryHandlerException
     */
    public function deactivate(ServerRequest $request): ResponseInterface
    {
        if (false === current_user_can(perm: 'deactivate:plugins')) {
            Devflow::$PHP->flash->error(message: t__(msgid: 'Access denied.', domain: 'devflow'));

            return $this->redirect(admin_url());
        }

        try {
            deactivate_plugin($request->getQueryParams()['id']);

            Devflow::$PHP->flash->success(t__(msgid: 'Plugin deactivated.', domain: 'devflow'));
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface | ReflectionException $e) {
            logger('error', $e->getMessage());

            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Plugin deactivation exception occurred and was logged.', domain: 'devflow')
            );
        }

        return $this->redirect($request->getServerParams()['HTTP_REFERER']);
    }
}
