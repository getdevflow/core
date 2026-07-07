<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Devflow;
use App\Infrastructure\Services\ExtensionService;
use Codefy\Framework\Http\BaseController;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
use JsonException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\EventDispatcher\ActionFilter\Action;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Http\Factories\JsonResponseFactory;
use Qubus\Http\ServerRequest;
use Qubus\Http\Session\SessionException;
use ReflectionException;

use function App\Shared\Helpers\activate_theme;
use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\cms_enqueue_css;
use function App\Shared\Helpers\cms_enqueue_js;
use function App\Shared\Helpers\current_user_can;
use function App\Shared\Helpers\deactivate_theme;
use function App\Shared\Helpers\get_option;
use function App\Shared\Helpers\is_main_site;
use function App\Shared\Helpers\is_super_admin;
use function App\Shared\Helpers\set_theme_available_for_subsites;
use function Codefy\Framework\Helpers\logger;
use function Codefy\Framework\Helpers\trans_html;
use function Codefy\Framework\Helpers\view;
use function in_array;
use function is_string;
use function strtoupper;

final class AdminThemeController extends BaseController
{
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
    public function themes(): ResponseInterface|string
    {
        if (false === current_user_can(perm: 'manage:themes')) {
            Devflow::$PHP->flash->error(
                message: trans_html('Access denied.')
            );

            return $this->redirect(admin_url());
        }

        Action::getInstance()->addAction('cms_admin_head', function () {
            cms_enqueue_css(
                config: 'default',
                asset: '//cdnjs.cloudflare.com/ajax/libs/ekko-lightbox/5.3.0/ekko-lightbox.css',
            );
        });
        Action::getInstance()->addAction('cms_admin_footer', function () {
            cms_enqueue_js(
                config: 'default',
                asset: '//cdnjs.cloudflare.com/ajax/libs/ekko-lightbox/5.3.0/ekko-lightbox.min.js'
            );
        });
        Action::getInstance()->addAction('cms_admin_footer', function () {
            $script = "<script>";
            $script .= "$(document).on('click', '[data-toggle=\"lightbox\"]', function(event) {";
                $script .= "event.preventDefault();";
                $script .= "$(this).ekkoLightbox();";
            $script .= "});";
            $script .= "</script>" . "\n";
            echo $script;
        });

        return view(
            template: 'framework::backend/admin/theme/index',
            data: ['title' => trans_html('Themes')]
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
     * @throws SessionException
     */
    public function activate(ServerRequest $request): ResponseInterface
    {
        if (false === current_user_can(perm: 'activate:themes')) {
            Devflow::$PHP->flash->error(
                message: trans_html('Access denied.')
            );

            return $this->redirect($request->getHeaderLine('Referer'));
        }

        try {
            activate_theme($request->getParsedBody()['theme_id']);

            Devflow::$PHP->flash->success(trans_html('Theme activated.'));
        } catch (\Exception $e) {
            logger(level: 'error', message: $e->getMessage());
            Devflow::$PHP->flash->error(
                message: trans_html('Theme activation exception occurred and was logged.')
            );
        }

        Action::getInstance()->doAction('activated_theme');

        return $this->redirect($request->getHeaderLine('Referer'));
    }

    /**
     * @param ServerRequest $request
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws SessionException
     */
    public function deactivate(ServerRequest $request): ResponseInterface
    {
        if (false === current_user_can(perm: 'deactivate:themes')) {
            Devflow::$PHP->flash->error(
                message: trans_html('Access denied.')
            );

            return $this->redirect($request->getHeaderLine('Referer'));
        }

        if (
                strtoupper($request->getMethod()) !== 'POST'
                && $request->getParsedBody()['theme_id'] !== get_option(key: 'site_theme')
        ) {
            Devflow::$PHP->flash->error(
                message: trans_html('Access denied.')
            );

            return $this->redirect(admin_url('theme/'));
        }

        try {
            deactivate_theme();

            Devflow::$PHP->flash->success(trans_html('Theme deactivated.'));
        } catch (\Exception $e) {
            logger(level: 'error', message: $e->getMessage());
            Devflow::$PHP->flash->error(
                message: trans_html('Theme deactivation exception occurred and was logged.')
            );
        }

        Action::getInstance()->doAction('deactivated_theme');

        return $this->redirect($request->getHeaderLine('Referer'));
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws JsonException
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Exception
     */
    public function networkThemeToggle(ServerRequest $request): ResponseInterface
    {
        if (! is_super_admin() || ! is_main_site()) {
            return JsonResponseFactory::create(
                [
                    'success' => false,
                    'message' => trans_html('Unauthorized.'),
                ],
                403
            );
        }

        $theme = $request->get('theme');
        $available = $request->get('available') === '1';

        if (! is_string($theme) || $theme === '') {
            return JsonResponseFactory::create(
                [
                    'success' => false,
                    'message' => trans_html('Invalid theme.'),
                ],
                422
            );
        }

        if ($available === false && $this->isThemePackageActivatedOnAnySite($theme)) {
            return JsonResponseFactory::create(
                [
                    'success' => false,
                    'message' => trans_html('Theme cannot be removed because it is activated on one or more sites.'),
                ],
                403
            );
        };

        set_theme_available_for_subsites($theme, $available);

        return JsonResponseFactory::create(
            [
                'success' => true,
                'theme' => $theme,
                'available' => $available,
            ],
        );
    }

    /**
     * @param string $themeClass
     * @return bool
     * @throws ReflectionException
     * @throws TypeException
     * @throws UnresolvableQueryHandlerException
     */
    private function isThemePackageActivatedOnAnySite(string $themeClass): bool
    {
        $service = new ExtensionService();
        $activeClasses = $service->getActiveThemeClassesAcrossSites();

        return in_array($themeClass, $activeClasses, true);
    }
}
