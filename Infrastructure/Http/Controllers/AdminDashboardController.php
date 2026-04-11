<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Devflow;
use Qubus\Expressive\Database;
use App\Shared\Services\ItemPoolObjectCacheFactory;
use App\Shared\Services\SimpleCacheObjectCacheFactory;
use Codefy\CommandBus\Exceptions\CommandPropertyNotFoundException;
use Codefy\Framework\Http\BaseController;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\EventDispatcher\ActionFilter\Action;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Http\ServerRequest;
use Qubus\Http\Session\SessionException;
use Qubus\Http\Session\SessionService;
use Qubus\Routing\Exceptions\NamedRouteNotFoundException;
use Qubus\Routing\Exceptions\RouteParamFailedConstraintException;
use Qubus\Routing\Router;
use Qubus\View\Renderer;
use ReflectionException;

use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\current_user_can;
use function App\Shared\Helpers\get_all_users;
use function Codefy\Framework\Helpers\view;
use function preg_filter;
use function Qubus\Security\Helpers\esc_html__;
use function Qubus\Security\Helpers\t__;

final class AdminDashboardController extends BaseController
{
    public function __construct(
        protected SessionService $sessionService,
        protected Router $router,
        protected Database $dfdb,
        protected Renderer $view
    ) {
        parent::__construct($sessionService, $router, $view);
    }

    /**
     * @return ResponseInterface|string
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NamedRouteNotFoundException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws RouteParamFailedConstraintException
     * @throws SessionException
     * @throws TypeException
     * @throws \Exception
     */
    public function index(): ResponseInterface|string
    {
        if (false === current_user_can(perm: 'access:admin')) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Access denied.', domain: 'devflow')
            );
            return $this->redirect($this->router->url(name: 'admin.login'));
        }

        return view(template: 'framework::backend/index', data: ['title' => 'Admin Dashboard']);
    }

    /**
     * @return ResponseInterface|string
     * @throws CommandPropertyNotFoundException
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NamedRouteNotFoundException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws RouteParamFailedConstraintException
     * @throws TypeException
     * @throws UnresolvableQueryHandlerException
     * @throws \Exception
     */
    public function snapshot(): ResponseInterface|string
    {
        if (false === current_user_can(perm: 'access:admin')) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Access denied.', domain: 'devflow')
            );
            return $this->redirect($this->router->url(name: 'admin.login'));
        }

        $users = get_all_users();

        return view(
            template: 'framework::backend/snapshot',
            data: ['title' => t__(msgid: 'System Snapshot', domain: 'devflow'), 'users' => count($users)]
        );
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
    public function flushCache(ServerRequest $request): ResponseInterface
    {
        if (false === current_user_can(perm: 'manage:settings')) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Access denied.', domain: 'devflow')
            );
        }

        $globalNamespaces = ['auto_updater','useremail','userlogin','users','usertoken','sites','sitekey','siteslug'];
        $siteNamespaces = preg_filter(
            pattern: '/^/',
            replacement: $this->dfdb->prefix,
            subject: [
                'content','contentauthor','contentslug','contenttype','content_attribute','products','productauthor',
                'productslug','productsku','product_attribute','options','database'
            ]
        );

        $namespaces = [...$siteNamespaces, ...$globalNamespaces];

        if (true === SimpleCacheObjectCacheFactory::make(namespace: $this->dfdb->prefix . 'user_attribute')->clear()) {
            ItemPoolObjectCacheFactory::make()->clear();

            foreach ($namespaces as $namespace) {
                SimpleCacheObjectCacheFactory::make(namespace: $namespace)->clear();
            }

            Devflow::$PHP->flash->success(
                esc_html__(string: 'Cache flushed successfully.', domain: 'devflow')
            );
        }

        /**
         * Fires after cache has been flushed.
         */
        Action::getInstance()->doAction('flush_cache');

        return $this->redirect($request->getHeaderLine(name: 'Referer'));
    }

    /**
     * @return ResponseInterface|string
     * @throws CommandPropertyNotFoundException
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws UnresolvableQueryHandlerException
     * @throws \Exception
     */
    public function media(): ResponseInterface|string
    {
        if (false === current_user_can(perm: 'manage:media')) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Access denied.', domain: 'devflow')
            );

            return $this->redirect(admin_url());
        }

        return view(template: 'framework::backend/media', data: ['title' => 'Media Library']);
    }
}
