<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Application\Devflow;
use App\Infrastructure\Persistence\Cache\ContentCachePsr16;
use App\Infrastructure\Persistence\Cache\ProductCachePsr16;
use App\Infrastructure\Services\Content\Event\ContentUpdated;
use App\Infrastructure\Services\Product\Event\ProductUpdated;
use App\Shared\Services\Parsecode;
use Codefy\Framework\Support\CodefyServiceProvider;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;

use function App\Shared\Helpers\load_active_plugins;
use function App\Shared\Helpers\load_active_theme;
use function Codefy\Framework\Helpers\trans_html;
use function Qubus\Security\Helpers\__observer;
use function sprintf;

final class CmsHelperServiceProvider extends CodefyServiceProvider
{
    /**
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function register(): void
    {
        $this->registerHooksFiltersAndHelpers();
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     * @throws TypeException
     */
    private function registerHooksFiltersAndHelpers(): void
    {
        if (!$this->codefy->isRunningInConsole()) {
            /**
             * Fires before the site's theme is loaded.
             */
            __observer()->action->doAction('before_setup_theme');

            load_active_plugins();
            load_active_theme();
        }
        /**
         * An action called to add the plugin's link
         * to the menu structure.
         */
        __observer()->action->doAction('admin_menu');
        /**
         * Registers & enqueues javascript to be printed in frontend footer section.
         */
        __observer()->action->doAction('enqueue_js');
        /**
         * Prints scripts and/or data before the ending body tag
         * of the front end.
         */
        __observer()->action->doAction('cms_footer');
        /**
         * Default actions and filters.
         */
        __observer()->action->addAction('cms_admin_head', 'App\Shared\Helpers\admin_enqueue_head', 5);
        __observer()->action->addAction('cms_admin_footer', 'App\Shared\Helpers\admin_enqueue_footer', 5);
        __observer()->action->addAction('login_form_top', 'App\Shared\Helpers\cms_login_form_show_message', 5);
        __observer()->action->addAction('admin_notices', 'App\Shared\Helpers\cms_dev_mode', 5);
        __observer()->action->addAction('admin_notices', 'App\Shared\Helpers\show_update_message', 5);
        __observer()->action->addAction('save_site', 'App\Shared\Helpers\new_site_schema', 5, 3);
        __observer()->action->addAction('save_site', 'App\Shared\Helpers\create_site_directories', 5, 3);
        __observer()->action->addAction('deleted_site', 'App\Shared\Helpers\delete_site_tables', 5, 2);
        __observer()->action->addAction('deleted_site', 'App\Shared\Helpers\delete_site_directories', 5, 2);
        __observer()->action->addAction(
            'reset_password_route',
            'App\Shared\Helpers\send_reset_password_email',
            5,
            2
        );
        __observer()->action->addAction('reassign_content', 'App\Shared\Helpers\reassign_content', 5, 2);
        __observer()->action->addAction('reassign_sites', 'App\Shared\Helpers\reassign_sites', 5, 2);
        __observer()->action->addAction(
            'password_change_email',
            'App\Shared\Helpers\send_password_change_email',
            5,
            3
        );
        __observer()->action->addAction(
            'email_change_email',
            'App\Shared\Helpers\send_email_change_email',
            5,
            2
        );
        __observer()->action->addAction('before_router_login', 'App\Shared\Helpers\does_site_exist', 6);
        __observer()->action->addAction('enqueue_cms_editor', 'App\Shared\Helpers\cms_editor', 5);
        __observer()->action->addAction('flush_cache', 'App\Shared\Helpers\populate_options_cache', 5);
        __observer()->action->addAction('maintenance_mode', 'App\Shared\Helpers\cms_maintenance_mode', 1);
        __observer()->filter->addFilter('the.body', [Parsecode::getInstance(), 'autop']);
        __observer()->filter->addFilter('the.body', [Parsecode::getInstance(), 'unAutop']);
        __observer()->filter->addFilter('the.body', [Parsecode::getInstance(), 'doParsecode'], 5);
        __observer()->filter->addFilter(
            'the.body',
            'App\Shared\Helpers\cms_encode_email',
            $this->codefy->configContainer->integer(key: 'cms.eae_filter_priority')
        );
        __observer()->filter->addFilter('cms.authenticate.user', '\App\Shared\Helpers\cms_authenticate', 5, 3);
        __observer()->filter->addFilter('cms.auth.cookie', '\App\Shared\Helpers\cms_set_auth_cookie', 5, 2);

        __observer()->filter->addFilter(
            'mail.xmailer',
            fn() => sprintf(trans_html(string: 'Devflow %s'), Devflow::release()),
            10,
        );
        /**
         * Fires once activated plugins have loaded.
         */
        __observer()->action->doAction('plugins_loaded');
        /**
         * Fires once the activated them has loaded.
         */
        __observer()->action->doAction('theme_loaded');
        /**
         * Fires after the site's theme is loaded.
         */
        __observer()->action->doAction('after_setup_theme');
    }

    /**
     * @return void
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function boot(): void
    {
        /** @var ListenerProviderInterface $provider */
        $provider = $this->codefy->make(name: ListenerProviderInterface::class);

        $provider->listen(ContentUpdated::class, function (ContentUpdated $event): void {
            ContentCachePsr16::clean($event->content);
        });

        $provider->listen(ProductUpdated::class, function (ProductUpdated $event): void {
            ProductCachePsr16::clean($event->product);
        });
    }
}
