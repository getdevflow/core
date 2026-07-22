<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Devflow;
use App\Domain\User\Query\FindUserByIdQuery;
use App\Domain\User\Validator\StoreUserValidator;
use App\Domain\User\Validator\UpdateUserProfileValidator;
use App\Domain\User\Validator\UpdateUserValidator;
use App\Domain\User\ValueObject\UserId;
use App\Infrastructure\Persistence\Cache\UserCachePsr16;
use App\Infrastructure\Services\NativePhpCookies;
use App\Infrastructure\Services\Queue\ResetPasswordNotification;
use App\Infrastructure\Services\User\Pipes\CastUserAttributesToInt;
use App\Infrastructure\Services\User\UserService;
use Codefy\CommandBus\Exceptions\CommandPropertyNotFoundException;
use Codefy\CommandBus\Exceptions\UnresolvableCommandHandlerException;
use Codefy\Framework\Http\BaseController;
use Codefy\Framework\Pipeline\Pipeline;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Key;
use Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Http\Cookies\CookiesResponse;
use Qubus\Http\Cookies\Factory\CookieFactory;
use Qubus\Http\Response;
use Qubus\Http\ServerRequest;
use Qubus\Http\Session\SessionException;
use ReflectionException;

use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\cms_delete_site_user;
use function App\Shared\Helpers\current_user_can;
use function App\Shared\Helpers\get_current_site_id;
use function App\Shared\Helpers\get_current_user_id;
use function App\Shared\Helpers\get_name;
use function App\Shared\Helpers\get_option;
use function App\Shared\Helpers\get_user_by;
use function App\Shared\Helpers\get_user_value;
use function App\Shared\Helpers\get_userdata;
use function App\Shared\Helpers\is_multisite;
use function App\Shared\Helpers\is_user_logged_in;
use function App\Shared\Helpers\login_url;
use function App\Shared\Helpers\remove_user_from_site;
use function App\Shared\Helpers\reset_password;
use function App\Shared\Helpers\site_url;
use function Codefy\Framework\Helpers\ask;
use function Codefy\Framework\Helpers\config;
use function Codefy\Framework\Helpers\logger;
use function Codefy\Framework\Helpers\queue;
use function Codefy\Framework\Helpers\storage_path;
use function Codefy\Framework\Helpers\trans;
use function Codefy\Framework\Helpers\trans_html;
use function Codefy\Framework\Helpers\view;
use function file_exists;
use function is_string;
use function parse_str;
use function Qubus\Error\Helpers\is_error;
use function Qubus\Support\Helpers\is_false__;
use function sprintf;
use function time;
use function unlink;

final class AdminUserController extends BaseController
{
    /**
     * @param ServerRequest $request
     * @param UserService $service
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     */
    public function userCreate(ServerRequest $request, UserService $service): ResponseInterface
    {
        $id = $service->createUser(
            StoreUserValidator::make(
                $request
            )
        );

        $request->withAttribute('USER_BODY', $request->getParsedBody());

        if (is_error($id)) {
            Devflow::$PHP->flash->error(
                message: $id->getMessage(),
            );
            return $this->redirect($request->getHeaderLine(name: 'Referer'));
        }

        return $this->redirect(admin_url(sprintf("user/%s/", $id)));
    }

    /**
     * @param ServerRequest $request
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function userCreateView(ServerRequest $request): ResponseInterface
    {
        if (false === current_user_can(perm: 'create:users')) {
            Devflow::$PHP->flash->error(
                message: trans_html('Access denied.'),
            );
            return $this->redirect(admin_url());
        }

        return view(
            template: 'framework::backend/admin/user/create',
            data: [
                'title' => trans_html('Create User'),
                'request' => $request->getAttribute('USER_BODY')
            ]
        );
    }

    /**
     * @param UserService $service
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function users(UserService $service): ResponseInterface
    {
        if (false === current_user_can(perm: 'manage:users')) {
            Devflow::$PHP->flash->error(
                message: trans_html('Access denied.'),
            );
            return $this->redirect(admin_url());
        }

        $users = $service->find();

        return view(
            template: 'framework::backend/admin/user/index',
            data: [
                'title' => trans_html(string: 'Users'),
                'users' => $users,
            ]
        );
    }

    /**
     * @param ServerRequest $request
     * @param UserService $service
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     */
    public function userChange(ServerRequest $request, UserService $service): ResponseInterface
    {
        $id = $service->updateUser(
            UpdateUserValidator::make(
                $request
            )
        );

        if (is_error($id)) {
            Devflow::$PHP->flash->error(
                message: $id->getMessage()
            );
            return $this->redirect($request->getHeaderLine(name: 'Referer'));
        }

        return $this->redirect($request->getHeaderLine(name: 'Referer'));
    }

    /**
     * @param string $userId
     * @param UserService $service
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function userView(string $userId, UserService $service): ResponseInterface
    {
        if (false === current_user_can(perm: 'update:users')) {
            Devflow::$PHP->flash->error(
                message: trans_html('Access denied.')
            );
            return $this->redirect(admin_url());
        }

        if ($userId === get_current_user_id()) {
            return $this->redirect(admin_url('user/profile'));
        }

        $user = $service->findById($userId);

        return view(
            template: 'framework::backend/admin/user/view',
            data: [
                'title' => sprintf(trans_html('View %s'), $user->login),
                'user' => $user,
            ]
        );
    }

    /**
     * @param ServerRequest $request
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     */
    public function userDelete(ServerRequest $request): ResponseInterface
    {
        if (false === current_user_can(perm: 'delete:users')) {
            Devflow::$PHP->flash->error(
                message: trans_html('Access denied.')
            );
            return $this->redirect(admin_url());
        }

        try {
            $oldUser = get_user_by(field: 'id', value: $request->getParsedBody()['user_id']);
            $assignId = (string) $request->getParsedBody()['assign_id'];

            if (is_multisite()) {
                remove_user_from_site(
                    userId: $request->getParsedBody()['user_id'],
                    params: [
                        'site_id' => get_current_site_id(),
                        'assign_id' => $assignId,
                        'role' => $oldUser->role
                    ]
                );
            } else {
                cms_delete_site_user(
                    userId: $request->getParsedBody()['user_id'],
                    params: [
                        'assign_id' => $assignId,
                        'role' => $oldUser->role
                    ]
                );
            }

            Devflow::$PHP->flash->success(
                message: Devflow::$PHP->flash->notice(num: 201),
            );
        } catch (
            CommandPropertyNotFoundException |
            ContainerExceptionInterface |
            TypeException |
            ReflectionException |
            Exception $e
        ) {
            logger('error', $e->getMessage());
            Devflow::$PHP->flash->error(
                message: trans_html('A removal exception occurred and was logged.')
            );
        }

        return $this->redirect($request->getHeaderLine(name: 'Referer'));
    }

    /**
     * @param ServerRequest $request
     * @return false|string
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws SessionException
     * @throws \Qubus\Exception\Exception
     * @throws CommandPropertyNotFoundException
     * @throws UnresolvableQueryHandlerException
     */
    public function userLookup(ServerRequest $request): false|string
    {
        if (!is_user_logged_in()) {
            Devflow::$PHP->flash->error(
                message: trans_html('Access denied.')
            );
        }

        $user = ask(new FindUserByIdQuery(['id' => UserId::fromNative($request->getParsedBody()['id'])]));

        $json = [
            'input#fname' => $user->fname, 'input#lname' => $user->lname,
            'input#email' => $user->email
        ];
        return json_encode($json);
    }

    /**
     * @param ServerRequest $request
     * @param string $userId
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     */
    public function userResetPassword(ServerRequest $request, string $userId): ResponseInterface
    {
        if (false === current_user_can(perm: 'update:users') || false === current_user_can(perm: 'reset:password')) {
            Devflow::$PHP->flash->error(
                message: trans_html('Access denied.')
            );
            return $this->redirect($request->getHeaderLine(name: 'Referer'));
        }

        try {
            $user = get_user_by(field: 'id', value: $userId);
            if (is_false__($user)) {
                Devflow::$PHP->flash->error(trans_html('User not found.'));
            }

            $password = reset_password($userId);
            if (is_string($password) && $password !== '') {
                Devflow::$PHP->flash->success(
                    sprintf(
                        trans('Password successfully updated for <strong>%s</strong>.'),
                        get_name($userId)
                    ),
                );
                UserCachePsr16::clean($user);
                queue(
                    new ResetPasswordNotification([
                        'login' => $user->login,
                        'pass' => $password,
                        'sitename' => (string) get_option(key: 'sitename'),
                        'email' => $user->email,
                        'url' => sprintf(site_url('admin/%s/'), config()->string(key: 'auth.login_route')),
                    ])
                )
                ->createItem();
            }

            Devflow::$PHP->flash->success(
                trans_html(
                    "The password reset email has been queued for sending.",
                )
            );
        } catch (
            NotFoundExceptionInterface |
            ContainerExceptionInterface |
            InvalidArgumentException |
            \Qubus\Exception\Exception |
            ReflectionException |
            UnresolvableCommandHandlerException |
            EnvironmentIsBrokenException $e
        ) {
            logger(level: 'error', message: $e->getMessage());
            Devflow::$PHP->flash->error(
                trans_html('Reset password exception occurred and was logged.')
            );
        }
        return $this->redirect($request->getHeaderLine(name: 'Referer'));
    }

    /**
     * @param ServerRequest $request
     * @param UserService $service
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function userProfile(ServerRequest $request, UserService $service): ResponseInterface
    {
        if ($request->getMethod() === 'POST') {
            $request = Devflow::$PHP->make(name: Pipeline::class)
                ->send($request)
                ->through([CastUserAttributesToInt::class])
                ->thenReturn();

            $update = $service->updateProfile(UpdateUserProfileValidator::make($request));
            if (is_error($update)) {
                Devflow::$PHP->flash->error($update->getMessage());
            }
            return $this->redirect(admin_url('user/profile/'));
        }

        return view(
            template: 'framework::backend/admin/user/profile',
            data: [
                'title' => trans_html('User Profile'),
                'user' => get_userdata(get_current_user_id())
            ]
        );
    }

    /**
     * @param ServerRequest $request
     * @param string $userId
     * @param Response $response
     * @return ResponseInterface
     * @throws CommandPropertyNotFoundException
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws UnresolvableQueryHandlerException
     * @throws \Qubus\Exception\Exception
     */
    public function userSwitchTo(ServerRequest $request, string $userId, Response $response): ResponseInterface
    {
        if (false === current_user_can(perm: 'switch:user')) {
            Devflow::$PHP->flash->error(
                message: trans_html('Access denied.')
            );

            return $this->redirect(admin_url());
        }

        try {
            $cookies = NativePhpCookies::factory();
            if (isset($request->getCookieParams()['USERCOOKIEID'])) {
                $switchCookie = [
                    'key' => 'SWITCH_USERBACK',
                    'id' => get_current_user_id(),
                    'token' => get_userdata(get_current_user_id())->token,
                    'remember' => 'yes',
                    'exp' => (int) get_option('cookieexpire', 172800) + time()
                ];

                $cookies->setSecureCookie($switchCookie);

                $vars = [];
                parse_str($cookies->get('USERCOOKIEID'), $vars);

                /**
                 * Checks to see if the cookie exists on the server.
                 * If it exists, we need to delete it.
                 */
                $file = storage_path('app/cookies/cookie.' . $vars['data']);
                if (file_exists($file)) {
                    unlink($file);
                }
                /**
                 * Delete the old cookies.
                 */
                $cookies->remove('USERCOOKIEID');
            }

            $authCookie = [
                'key' => 'USERCOOKIEID',
                'id' => $userId,
                'token' => get_user_value($userId, 'token'),
                'remember' => 'yes',
                'exp' => (int) get_option('cookieexpire', 172800) + time()
            ];

            $cookies->setSecureCookie($authCookie);
            /** @var CookieFactory $cookieFactory */
            $cookieFactory = Devflow::$PHP->make(name: CookieFactory::class);

            $response = CookiesResponse::set(
                response: $response,
                setCookieCollection: $cookieFactory->make(
                    name: config()->string(key: 'auth.cookie_name', default: 'USERSESSID'),
                    value: Crypto::encrypt(
                        plaintext: get_user_value(id: $userId, field: 'token'),
                        key: Key::loadFromAsciiSafeString(config()->string(key: 'app.crypto_key'))
                    ),
                    maxAge: (int) get_option(key: 'cookieexpire', default: 172800) + time()
                )
            )
            ->withHeader('Location', admin_url())
            ->withStatus(302);

            Devflow::$PHP->flash->success(
                message: trans_html('User switching was successful.')
            );

            return $response;
        } catch (
            NotFoundExceptionInterface |
            ContainerExceptionInterface |
            \Qubus\Exception\Exception |
            ReflectionException |
            Exception $e
        ) {
            logger(level: 'error', message: $e->getMessage());
            Devflow::$PHP->flash->error(
                trans_html('User switching exception occurred and was logged.')
            );
        }

        return $this->redirect($request->getHeaderLine(name: 'Referer'));
    }

    /**
     * @param ServerRequest $request
     * @param string $userId
     * @param Response $response
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws SessionException
     * @throws \Qubus\Exception\Exception
     */
    public function userSwitchBack(ServerRequest $request, string $userId, Response $response): ResponseInterface
    {
        if (!is_user_logged_in()) {
            Devflow::$PHP->flash->error(
                message: trans_html('Access denied.')
            );

            return $this->redirect(login_url());
        }

        try {
            $cookies = NativePhpCookies::factory();

            if ($cookies->get('USERCOOKIEID') === null || $cookies->get('USERCOOKIEID') === '') {
                Devflow::$PHP->flash->error(
                    message: trans_html('Cookie is not properly set for user switching.')
                );

                $this->redirect(admin_url());
            }

            $vars1 = [];
            parse_str($cookies->get('USERCOOKIEID'), $vars1);
            /**
             * Checks to see if the cookie exists on the server.
             * If it exists, we need to delete it.
             */
            $file1 = storage_path('app/cookies/cookie' . $vars1['data']);
            if (file_exists($file1)) {
                unlink($file1);
            }
            $cookies->remove('USERCOOKIEID');

            /**
             * After the login as user cookies has been
             * removed from the server and the browser,
             * we need to set fresh cookies for the
             * original logged-in user.
             */
            $switchCookie = [
                'key' => 'USERCOOKIEID',
                'id' => $userId,
                'token' => get_user_value(id: $userId, field: 'token'),
                'remember' => 'yes',
                'exp' => (int) get_option('cookieexpire', 172800) + time()
            ];
            $cookies->setSecureCookie($switchCookie);

            $vars2 = [];
            parse_str($cookies->get('SWITCH_USERBACK'), $vars2);
            /**
             * Checks to see if the cookie exists on the server.
             * If it exists, we need to delete it.
             */
            $file2 = storage_path('app/cookies/cookie' . $vars2['data']);
            if (file_exists($file2)) {
                unlink($file2);
            }
            $cookies->remove('SWITCH_USERBACK');
            /** @var CookieFactory $cookieFactory */
            $cookieFactory = Devflow::$PHP->make(name: CookieFactory::class);

            $response = CookiesResponse::set(
                response: $response,
                setCookieCollection: $cookieFactory->make(
                    name: config()->string(key: 'auth.cookie_name', default: 'USERSESSID'),
                    value: Crypto::encrypt(
                        plaintext: get_user_value(id: $userId, field: 'token'),
                        key: Key::loadFromAsciiSafeString(config()->string(key: 'app.crypto_key'))
                    ),
                    maxAge: (int) get_option(key: 'cookieexpire', default: 172800) + time()
                )
            )
            ->withHeader('Location', $request->getHeaderLine(name: 'Referer'))
            ->withStatus(302);

            Devflow::$PHP->flash->success(
                message: trans_html('Switching back to previous user session was successful.')
            );

            return $response;
        } catch (
            \Qubus\Exception\Exception |
            ReflectionException |
            NotFoundExceptionInterface |
            ContainerExceptionInterface |
            Exception $e
        ) {
            logger(level: 'error', message: $e->getMessage());
            Devflow::$PHP->flash->error(
                trans_html('User switch back exception occurred and was logged.')
            );
        }

        return $this->redirect($request->getHeaderLine(name: 'Referer'));
    }
}
