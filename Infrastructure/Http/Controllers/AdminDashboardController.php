<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Devflow;
use App\Infrastructure\Persistence\Repository\ExtensionRepository;
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
use Qubus\Routing\Exceptions\NamedRouteNotFoundException;
use Qubus\Routing\Exceptions\RouteParamFailedConstraintException;
use ReflectionException;

use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\current_user_can;
use function App\Shared\Helpers\get_current_site_key;
use function App\Shared\Helpers\get_users_by_site_key;
use function App\Shared\Helpers\global_option_cache;
use function App\Shared\Helpers\is_main_site;
use function App\Shared\Helpers\is_user_logged_in;
use function Codefy\Framework\Helpers\base_path;
use function Codefy\Framework\Helpers\trans;
use function Codefy\Framework\Helpers\view;
use function preg_filter;

final class AdminDashboardController extends BaseController
{
    /**
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Exception
     */
    public function index(): ResponseInterface
    {
        if (!is_user_logged_in()) {
            Devflow::$PHP->flash->error(
                message: trans('Access denied.')
            );
            return $this->redirect(Devflow::$PHP->configContainer->string(key: 'auth.redirect_guests_to'));
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
                message: trans('Access denied.')
            );
            return $this->redirect($this->router->url(name: 'admin.login'));
        }

        $users = get_users_by_site_key(get_current_site_key());

        return view(
            template: 'framework::backend/snapshot',
            data: ['title' => trans('System Snapshot'), 'users' => count($users)]
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
                message: trans('Access denied.')
            );
        }

        $globalNamespaces = ['auto_updater','useremail','userlogin','users','usertoken','sites','sitekey','siteslug'];
        $siteNamespaces = preg_filter(
            pattern: '/^/',
            replacement: Devflow::db()->prefix,
            subject: [
                'content','contentauthor','contentslug','contenttype','content_attribute','products','productauthor',
                'productslug','productsku','product_attribute','options'
            ]
        );

        $namespaces = [...$siteNamespaces, ...$globalNamespaces];

        if (true === SimpleCacheObjectCacheFactory::make(namespace: Devflow::db()->prefix . 'user_attribute')->clear()) {
            ItemPoolObjectCacheFactory::make()->clear();

            if (is_main_site()) {
                global_option_cache()->clear();
            }

            $repository = new ExtensionRepository(
                composerLockPath: base_path('composer.lock')
            );
            $repository->clearCache();


            foreach ($namespaces as $namespace) {
                SimpleCacheObjectCacheFactory::make(namespace: $namespace)->clear();
            }

            Devflow::$PHP->flash->success(
                trans(string: 'Cache flushed successfully.')
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
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Exception
     */
    public function media(): ResponseInterface|string
    {
        if (false === current_user_can(perm: 'manage:media')) {
            Devflow::$PHP->flash->error(
                message: trans('Access denied.')
            );

            return $this->redirect(admin_url());
        }

        return view(template: 'framework::backend/media', data: ['title' => 'Media Library']);
    }
}
