<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Devflow;
use App\Infrastructure\Persistence\Cache\UserCachePsr16;
use App\Infrastructure\Services\Queue\ResetPasswordNotification;
use App\Infrastructure\Services\User\PasswordRecovery;
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
use Qubus\Routing\Psr7Router;
use ReflectionException;

use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\cms_authenticate_user;
use function App\Shared\Helpers\cms_clear_auth_cookie;
use function App\Shared\Helpers\cms_safe_redirect_url;
use function App\Shared\Helpers\current_user_can;
use function App\Shared\Helpers\get_current_user_id;
use function App\Shared\Helpers\get_option;
use function App\Shared\Helpers\get_userdata;
use function App\Shared\Helpers\login_url;
use function App\Shared\Helpers\site_url;
use function Codefy\Framework\Helpers\config;
use function Codefy\Framework\Helpers\logger;
use function Codefy\Framework\Helpers\queue;
use function Codefy\Framework\Helpers\trans_html;
use function Codefy\Framework\Helpers\view;
use function Qubus\Security\Helpers\__observer;

final class AdminAuthController extends BaseController
{
    public function __construct(private readonly Psr7Router $router)
    {
    }

    /**
     * @param ServerRequest $request
     * @return ResponseInterface
     * @throws Exception
     */
    public function auth(ServerRequest $request): ResponseInterface
    {
        if ($request->getMethod() !== 'POST') {
            return new \Qubus\Http\Response(status: 405, headers: ['Allow' => 'POST']);
        }

        try {
            /**
             * Filters where the admin should be redirected after successful login.
             */
            $loginLink = cms_safe_redirect_url(
                candidate: __observer()->filter->applyFilter(
                    'admin.login.redirect',
                    admin_url()
                ),
                fallback: admin_url()
            );

            $input = (array) $request->getParsedBody();
            $authentication = cms_authenticate_user(
                login: is_string($input['user_login'] ?? null) ? $input['user_login'] : '',
                password: is_string($input['user_pass'] ?? null) ? $input['user_pass'] : '',
                rememberme: ($input['rememberme'] ?? null) === 'yes' ? 'yes' : 'no'
            );

            if ($authentication instanceof ResponseInterface) {
                return $authentication;
            }

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
            logger('error', $e->getMessage());
            Devflow::$PHP->flash->error(trans_html('Login exception occurred and was logged.'));
        }

        return $this->redirect(
            cms_safe_redirect_url(
                candidate: $request->getHeaderLine(name: 'Referer'),
                fallback: login_url()
            )
        );
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
                'title' => trans_html('Login'),
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
        if ($request->getMethod() !== 'POST') {
            return new \Qubus\Http\Response(status: 405, headers: ['Allow' => 'POST']);
        }

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
                message: trans_html('You are already logged out.')
            );
            return $this->redirect(
                cms_safe_redirect_url(candidate: $redirectLink, fallback: login_url())
            );
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
     * @param PasswordRecovery $recovery
     * @return ResponseInterface
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function resetPasswordChange(ServerRequest $request, PasswordRecovery $recovery): ResponseInterface
    {
        if ($request->getMethod() !== 'POST') {
            return new \Qubus\Http\Response(status: 405, headers: ['Allow' => 'POST']);
        }

        $body = (array) $request->getParsedBody();

        try {
            if (isset($body['token'])) {
                $id = is_string($body['user_id'] ?? null) ? $body['user_id'] : '';
                $password = is_string($body['password'] ?? null) ? $body['password'] : '';
                $valid = is_string($body['token']) && $password === ($body['password_confirmation'] ?? null)
                && $recovery->reset($id, $body['token'], $password);
                if ($valid) {
                    UserCachePsr16::clean(get_userdata($id));
                    cms_clear_auth_cookie();
                    Devflow::$PHP->flash->success(trans_html('Password updated. Please sign in.'));
                } else {
                    Devflow::$PHP->flash->error(trans_html('Invalid or expired recovery link, or invalid password.'));
                }
            } else {
                $email = is_string($body['email'] ?? null) ? $body['email'] : '';
                $recoveryData = $recovery->request($email);
                if ($recoveryData !== null) {
                    queue(new ResetPasswordNotification([
                        'login' => $recoveryData['login'],
                        'sitename' => (string) get_option('sitename'),
                        'email' => $recoveryData['email'],
                        'url' => site_url(config()->string('auth.password_reset_route', 'admin/password/reset/'))
                            . '?' . http_build_query([
                                'user_id' => $recoveryData['id'], 'token' => $recoveryData['token'],
                            ]),
                    ]))->createItem();
                }
                Devflow::$PHP->flash->success(
                    trans_html('If the account exists, a password recovery link will be sent.')
                );
            }
        } catch (\Exception | ContainerExceptionInterface $e) {
            logger('error', $e->getMessage());
            Devflow::$PHP->flash->error(trans_html('Unable to process password recovery. Please try again.'));
        }
        return $this->redirect(login_url());
    }

    /**
     * @throws \Exception
     */
    public function resetPasswordView(ServerRequest $request): ResponseInterface
    {
        $query = $request->getQueryParams();
        if (!isset($query['token'])) {
            return view(template: 'framework::backend/auth/reset');
        }
        $token = is_string($query['token']) ? $query['token'] : '';
        $id = is_string($query['user_id'] ?? null) ? $query['user_id'] : '';
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $action = $escape(site_url(config()->string('auth.password_reset_route', 'admin/password/reset/')));
        $token = $escape($token);
        $id = $escape($id);
        $csrf = csrf_field();
        return \Qubus\Http\Factories\HtmlResponseFactory::create(<<<HTML
<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width">
<title>Reset password</title><main><h1>Reset password</h1><form method="post" action="{$action}">
{$csrf}<input type="hidden" name="user_id" value="{$id}"><input type="hidden" name="token" value="{$token}">
<p><label>New password <input type="password" name="password" autocomplete="new-password" required></label></p>
<p><label>Confirm password
<input type="password" name="password_confirmation" autocomplete="new-password" required></label></p>
<button type="submit">Reset password</button></form></main></html>
HTML)->withHeader('Cache-Control', 'no-store')->withHeader('Referrer-Policy', 'no-referrer');
    }
}
