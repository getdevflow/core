<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Infrastructure\Services\Dashboard\DashboardWidgetRegistry;
use App\Infrastructure\Services\Dashboard\NativeDashboardWidgets;
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
use Qubus\Http\Factories\JsonResponseFactory;
use Qubus\Http\ServerRequest;
use Qubus\Routing\Exceptions\NamedRouteNotFoundException;
use Qubus\Routing\Exceptions\RouteParamFailedConstraintException;
use ReflectionException;
use Throwable;

use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\current_user_can;
use function App\Shared\Helpers\get_current_site_key;
use function App\Shared\Helpers\get_current_site_id;
use function App\Shared\Helpers\get_current_user_id;
use function App\Shared\Helpers\get_user_attribute;
use function App\Shared\Helpers\get_users_by_site_key;
use function App\Shared\Helpers\global_option_cache;
use function App\Shared\Helpers\has_site_user_record;
use function App\Shared\Helpers\is_main_site;
use function App\Shared\Helpers\is_user_logged_in;
use function App\Shared\Helpers\update_user_attribute;
use function Codefy\Framework\Helpers\base_path;
use function Codefy\Framework\Helpers\logger;
use function Codefy\Framework\Helpers\trans_html;
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
                message: trans_html('Access denied.')
            );
            return $this->redirect(Devflow::$PHP->configContainer->string(key: 'auth.redirect_guests_to'));
        }

        $siteId = get_current_site_id();
        $userId = get_current_user_id();
        $editable = has_site_user_record($siteId, $userId);
        $widgets = $this->registeredWidgets();
        $can = static fn (string $permission): bool => current_user_can($permission);

        if ($editable) {
            try {
                $savedLayout = get_user_attribute(
                    userId: $userId,
                    key: 'dashboard.widgets',
                    siteId: $siteId,
                    default: null,
                );
            } catch (Throwable) {
                $savedLayout = null;
            }

            $layout = $widgets->resolveLayout($savedLayout, $can);
        } else {
            $layout = $widgets->defaultLayout($can);
        }

        Action::getInstance()->addAction(
            hook: 'cms_admin_footer',
            callback: 'App\Shared\Helpers\admin_dashboard_js',
            priority: 5,
        );

        return view(
            template: 'framework::backend/index',
            data: [
                'title' => trans_html('Admin Dashboard'),
                'dashboardWidgets' => $widgets->all($can),
                'dashboardLayout' => $layout,
                'inactiveDashboardWidgets' => $widgets->inactive($layout, $can),
                'dashboardEditable' => $editable,
            ],
        );
    }

    /**
     * Persist the current user's dashboard widget selection and order.
     *
     * @param ServerRequest $request
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws \Exception
     */
    public function saveWidgets(ServerRequest $request): ResponseInterface
    {
        if (! is_user_logged_in()) {
            return JsonResponseFactory::create(
                ['success' => false, 'message' => trans_html('Authentication required.')],
                401,
            );
        }

        $siteId = get_current_site_id();
        $userId = get_current_user_id();

        // Network super admins deliberately have a fixed dashboard and no site-user record.
        if (! has_site_user_record($siteId, $userId)) {
            return JsonResponseFactory::create(
                ['success' => false, 'message' => trans_html('This dashboard cannot be customized.')],
                403,
            );
        }

        $body = $request->getParsedBody();
        $requestedLayout = is_array($body) ? [
            'left' => $body['left'] ?? [],
            'right' => $body['right'] ?? [],
        ] : [];
        $widgets = $this->registeredWidgets();
        $layout = $widgets->sanitizeLayout(
            $requestedLayout,
            static fn (string $permission): bool => current_user_can($permission),
        );

        try {
            update_user_attribute(
                userId: $userId,
                key: 'dashboard.widgets',
                value: $layout,
                siteId: $siteId,
            );
        } catch (Throwable $exception) {
            logger(
                level: 'error',
                message: $exception->getMessage(),
                context: ['Dashboard' => 'Unable to save widget layout.'],
            );

            return JsonResponseFactory::create(
                ['success' => false, 'message' => trans_html('The dashboard layout could not be saved.')],
                500,
            );
        }

        return JsonResponseFactory::create([
            'success' => true,
            'message' => trans_html('Dashboard saved.'),
            'layout' => $layout,
        ]);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    private function registeredWidgets(): DashboardWidgetRegistry
    {
        $widgets = DashboardWidgetRegistry::getInstance();
        $widgets->clear();

        NativeDashboardWidgets::register($widgets);

        /**
         * Register dashboard widgets after plugins and the active theme have loaded.
         *
         * @param DashboardWidgetRegistry $widgets
         */
        Action::getInstance()->doAction('dashboard_widgets_init', $widgets);

        return $widgets;
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
        if (false === current_user_can(perm: 'change:settings')) {
            Devflow::$PHP->flash->error(
                message: trans_html('Access denied.')
            );
            return $this->redirect($this->router->url(name: 'admin.login'));
        }

        $users = get_users_by_site_key(get_current_site_key());

        return view(
            template: 'framework::backend/snapshot',
            data: ['title' => trans_html('System Snapshot'), 'users' => count($users)]
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
        if (false === current_user_can(perm: 'flush:cache')) {
            Devflow::$PHP->flash->error(
                message: trans_html('Access denied.')
            );

            return $this->redirect($request->getHeaderLine('Referer'));
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
                trans_html(string: 'Cache flushed successfully.')
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
                message: trans_html('Access denied.')
            );

            return $this->redirect(admin_url());
        }

        return view(template: 'framework::backend/media', data: ['title' => trans_html('Media Library')]);
    }
}
