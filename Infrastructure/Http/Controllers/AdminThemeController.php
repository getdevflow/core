<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Devflow;
use Codefy\Framework\Http\BaseController;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\EventDispatcher\ActionFilter\Action;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Http\ServerRequest;
use Qubus\Http\Session\SessionException;
use ReflectionException;

use function App\Shared\Helpers\activate_theme;
use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\cms_enqueue_css;
use function App\Shared\Helpers\cms_enqueue_js;
use function App\Shared\Helpers\current_user_can;
use function App\Shared\Helpers\deactivate_theme;
use function App\Shared\Helpers\is_user_logged_in;
use function Codefy\Framework\Helpers\logger;
use function Codefy\Framework\Helpers\view;
use function Qubus\Security\Helpers\t__;

final class AdminThemeController extends BaseController
{
    /**
     * @param ServerRequest $request
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
    public function themes(ServerRequest $request): ResponseInterface|string
    {
        if (false === current_user_can(perm: 'manage:themes')) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Access denied.', domain: 'devflow')
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
            $script .= "</script>;";
            echo $script;
        });

        return view(
            template: 'framework::backend/admin/theme/index',
            data: ['title' => t__(msgid: 'Themes', domain: 'devflow')]
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
        if (false === is_user_logged_in()) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Access denied.', domain: 'devflow')
            );
            return $this->redirect(admin_url());
        }

        try {
            activate_theme($request->getQueryParams()['id']);

            Devflow::$PHP->flash->success(t__(msgid: 'Theme activated.', domain: 'devflow'));
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface | ReflectionException $e) {
            logger(level: 'error', message: $e->getMessage());
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Theme activation exception occurred and was logged.', domain: 'devflow')
            );
        }

        Action::getInstance()->doAction('activated_theme');

        return $this->redirect($request->getServerParams()['HTTP_REFERER']);
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
        if (false === is_user_logged_in()) {
            Devflow::$PHP->flash->error(message: t__(msgid: 'Access denied.', domain: 'devflow'));

            return $this->redirect(admin_url());
        }

        try {
            deactivate_theme();

            Devflow::$PHP->flash->success(t__(msgid: 'Theme deactivated.', domain: 'devflow'));
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface | ReflectionException $e) {
            logger(level: 'error', message: $e->getMessage());
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Theme deactivation exception occurred and was logged.', domain: 'devflow')
            );
        }

        Action::getInstance()->doAction('deactivated_theme');

        return $this->redirect($request->getServerParams()['HTTP_REFERER']);
    }
}
