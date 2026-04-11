<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Devflow;
use App\Domain\User\Model\User;
use Qubus\Expressive\Database;
use App\Infrastructure\Services\UserAuth;
use Codefy\Framework\Http\BaseController;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;
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
use function App\Shared\Helpers\cms_authenticate_user;
use function App\Shared\Helpers\cms_clear_auth_cookie;
use function App\Shared\Helpers\cms_update_user;
use function App\Shared\Helpers\generate_random_password;
use function App\Shared\Helpers\get_user_by;
use function App\Shared\Helpers\login_url;
use function App\Shared\Helpers\site_url;
use function Codefy\Framework\Helpers\config;
use function Codefy\Framework\Helpers\logger;
use function Codefy\Framework\Helpers\view;
use function Qubus\Error\Helpers\is_error;
use function Qubus\Security\Helpers\__observer;
use function Qubus\Security\Helpers\t__;
use function Qubus\Support\Helpers\is_false__;

final class AdminAuthController extends BaseController
{
    public function __construct(
        protected SessionService $sessionService,
        protected Router $router,
        protected UserAuth $user,
        protected Database $dfdb,
        protected Renderer $view
    ) {
        parent::__construct($sessionService, $router, $view);
    }

    /**
     * @param ServerRequest $request
     * @return ResponseInterface
     */
    public function auth(ServerRequest $request): ResponseInterface
    {
        try {
            /**
             * Filters where the admin should be redirected after successful login.
             */
            $loginLink = __observer()->filter->applyFilter(
                'admin.login.redirect',
                admin_url()
            );

            cms_authenticate_user(
                login: $request->getParsedBody()['user_login'],
                password: $request->getParsedBody()['user_pass'],
                rememberme: $request->getParsedBody()['rememberme'] ?? 'no'
            );

            /**
             * Fires after the user has logged in.
             *
             * @param $request ServerRequest
             */
            __observer()->action->doAction('login_init', $request);

            return $this->redirect($loginLink);
        } catch (
            NotFoundExceptionInterface |
            ContainerExceptionInterface |
            InvalidArgumentException |
            SessionException |
            Exception |
            ReflectionException $e
        ) {
            Devflow::$PHP->flash->error($e->getMessage());
        }

        return $this->redirect($request->getHeaderLine(name: 'Referer'));
    }

    /**
     * @param ServerRequest $request
     * @return ResponseInterface
     * @throws Exception
     * @throws NamedRouteNotFoundException
     * @throws ReflectionException
     * @throws RouteParamFailedConstraintException
     * @throws TypeException
     * @throws \Exception
     */
    public function login(ServerRequest $request): ResponseInterface
    {
        if (true === $this->user->can(permissionName: 'access:admin')) {
            return $this->redirect(admin_url());
        }

        return view(
            template: 'framework::backend/auth/index',
            data: [
                'title' => t__(msgid: 'Login', domain: 'devflow'),
                'url' => site_url($this->router->url(name: 'admin.auth')),
            ]
        );
    }

    /**
     * @param ServerRequest $request
     * @return ResponseInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NamedRouteNotFoundException
     * @throws ReflectionException
     * @throws RouteParamFailedConstraintException
     * @throws SessionException
     * @throws TypeException
     */
    public function logout(ServerRequest $request): ResponseInterface
    {
        if (
            $request->getHeaderLine(name: 'Referer')!==null &&
                !str_contains($request->getHeaderLine(name: 'Referer'), 'admin')
        ) {
            $redirectLink = __observer()->filter->applyFilter(
                'user.logout.redirect',
                login_url()
            );
        } else {
            $redirectLink = __observer()->filter->applyFilter(
                'admin.logout.redirect',
                admin_url()
            );
        }

        if (false === $this->user->can(permissionName: 'access:admin')) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'You are already logged out.', domain: 'devflow')
            );
            return $this->redirect($redirectLink);
        }

        /**
         * This function is documented in core/Shared/Helpers/auth.php.
         */
        cms_clear_auth_cookie();

        /**
         * Fires after a user has logged out.
         *
         * @param $request ServerRequest
         */
        __observer()->action->doAction('cms_logout', $request);

        return $this->redirect($this->router->url(name: 'admin.login'));
    }

    /**
     * @param ServerRequest $request
     * @return ResponseInterface
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function resetPasswordChange(ServerRequest $request): ResponseInterface
    {
        try {
            $currentUser = get_user_by('email', $request->getParsedBody()['email']);
            if (is_false__($currentUser)) {
                Devflow::$PHP->flash->error(
                    message: t__(msgid: 'Request error.', domain: 'devflow')
                );

                return $this->redirect($request->getHeaderLine(name: 'Referer'));
            }

            if ('' !== $currentUser->id) {
                $password = generate_random_password(config()->integer(key: 'cms.password_length'));
                $newUser = new User($this->dfdb)->findBy(field: 'email', value: $currentUser->email);

                foreach ($currentUser->toArray() as $key => $value) {
                    unset($newUser->pass);
                    $newUser->{$key} = $value;
                }
                $newUser->pass = $password;
                $update = cms_update_user($newUser);

                if (is_error($update)) {
                    Devflow::$PHP->flash->error(
                        message: t__(msgid: 'Request error.', domain: 'devflow')
                    );
                } else {
                    Devflow::$PHP->flash->success(
                        message: t__(
                            msgid: 'A new password was sent to your email. May take a few minutes to arrive, so please be patient',
                            domain: 'devflow'
                        )
                    );
                }
            }

            return $this->redirect($this->router->url(name: 'admin.login'));
        } catch (\Exception | NotFoundExceptionInterface | ContainerExceptionInterface $e) {
            logger('error', $e->getMessage());
            Devflow::$PHP->flash->error(
                t__(msgid: 'Password reset exception occurred and was logged.', domain: 'devflow')
            );
        }

        return $this->redirect($request->getHeaderLine(name: 'Referer'));
    }

    /**
     * @throws \Exception
     */
    public function resetPasswordView(): ResponseInterface
    {
        return view(template: 'framework::backend/auth/reset');
    }
}
