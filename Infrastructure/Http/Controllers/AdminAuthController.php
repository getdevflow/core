<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Devflow;
use App\Infrastructure\Persistence\Cache\UserCachePsr16;
use App\Infrastructure\Services\Queue\ResetPasswordNotification;
use Codefy\Framework\Http\BaseController;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Http\ServerRequest;
use Qubus\Http\Session\SessionException;
use Qubus\Routing\Exceptions\NamedRouteNotFoundException;
use Qubus\Routing\Exceptions\RouteParamFailedConstraintException;
use ReflectionException;

use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\cms_authenticate_user;
use function App\Shared\Helpers\cms_clear_auth_cookie;
use function App\Shared\Helpers\current_user_can;
use function App\Shared\Helpers\get_current_user_id;
use function App\Shared\Helpers\get_option;
use function App\Shared\Helpers\get_user_by;
use function App\Shared\Helpers\get_userdata;
use function App\Shared\Helpers\login_url;
use function App\Shared\Helpers\reset_password;
use function App\Shared\Helpers\site_url;
use function Codefy\Framework\Helpers\config;
use function Codefy\Framework\Helpers\logger;
use function Codefy\Framework\Helpers\queue;
use function Codefy\Framework\Helpers\trans;
use function Codefy\Framework\Helpers\view;
use function Qubus\Error\Helpers\is_error;
use function Qubus\Security\Helpers\__observer;
use function Qubus\Support\Helpers\is_false__;
use function sprintf;

final class AdminAuthController extends BaseController
{
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
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NamedRouteNotFoundException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws RouteParamFailedConstraintException
     * @throws TypeException
     * @throws \Exception
     */
    public function login(): ResponseInterface
    {
        if (true === current_user_can(perm: 'access:admin')) {
            return $this->redirect(admin_url());
        }

        return view(
            template: 'framework::backend/auth/index',
            data: [
                'title' => trans('Login'),
                'url' => site_url($this->router->url(name: 'admin.auth')),
            ]
        );
    }

    /**
     * @param ServerRequest $request
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NamedRouteNotFoundException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws RouteParamFailedConstraintException
     * @throws TypeException
     */
    public function logout(ServerRequest $request): ResponseInterface
    {
        if (
            $request->getHeaderLine(name: 'Referer') !== null &&
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

        if (false === current_user_can(perm: 'access:admin')) {
            Devflow::$PHP->flash->error(
                message: trans('You are already logged out.')
            );
            return $this->redirect($redirectLink);
        }

        UserCachePsr16::clean(get_userdata(get_current_user_id()));

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
                    message: trans('Request error.')
                );

                return $this->redirect($request->getHeaderLine(name: 'Referer'));
            }

            if ('' !== $currentUser->id) {
                $password = reset_password($currentUser->id);

                if (is_error($password)) {
                    Devflow::$PHP->flash->error(
                        message: trans('Request error.')
                    );
                } else {
                    queue(
                        new ResetPasswordNotification([
                            'login' => $currentUser->login,
                            'pass' => $password,
                            'sitename' => (string) get_option(key: 'sitename'),
                            'email' => $currentUser->email,
                            'url' => sprintf(site_url('admin/%s/'), config()->string(key: 'auth.login_route'))
                        ])
                    )
                    ->createItem();

                    UserCachePsr16::clean($currentUser);

                    Devflow::$PHP->flash->success(
                        message: trans(
                            'A new password was sent to your email. May take a few minutes to arrive, so please be patient',
                        )
                    );
                }
            }

            return $this->redirect($this->router->url(name: 'admin.login'));
        } catch (\Exception | NotFoundExceptionInterface | ContainerExceptionInterface $e) {
            logger('error', $e->getMessage());
            Devflow::$PHP->flash->error(
                trans('Password reset exception occurred and was logged.')
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
