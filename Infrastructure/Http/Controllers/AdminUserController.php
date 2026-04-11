<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Devflow;
use App\Domain\User\Model\User;
use App\Domain\User\Query\FindUserByIdQuery;
use App\Domain\User\ValueObject\UserId;
use App\Infrastructure\Services\NativePhpCookies;
use Codefy\CommandBus\Exceptions\CommandCouldNotBeHandledException;
use Codefy\CommandBus\Exceptions\CommandPropertyNotFoundException;
use Codefy\CommandBus\Exceptions\UnresolvableCommandHandlerException;
use Codefy\Framework\Http\BaseController;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Key;
use Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\EventDispatcher\ActionFilter\Action;
use Qubus\Exception\Data\TypeException;
use Qubus\Http\Cookies\CookiesResponse;
use Qubus\Http\Cookies\Factory\CookieFactory;
use Qubus\Http\Factories\JsonResponseFactory;
use Qubus\Http\Response;
use Qubus\Http\ServerRequest;
use Qubus\Http\Session\SessionException;
use ReflectionException;

use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\cms_insert_user;
use function App\Shared\Helpers\cms_update_user;
use function App\Shared\Helpers\current_user_can;
use function App\Shared\Helpers\get_all_users;
use function App\Shared\Helpers\get_current_site_id;
use function App\Shared\Helpers\get_current_user_id;
use function App\Shared\Helpers\get_name;
use function App\Shared\Helpers\get_option;
use function App\Shared\Helpers\get_user_by;
use function App\Shared\Helpers\get_user_value;
use function App\Shared\Helpers\get_userdata;
use function App\Shared\Helpers\is_user_logged_in;
use function App\Shared\Helpers\queue_new_user_email;
use function App\Shared\Helpers\remove_user_from_site;
use function App\Shared\Helpers\reset_password;
use function array_merge;
use function Codefy\Framework\Helpers\ask;
use function Codefy\Framework\Helpers\config;
use function Codefy\Framework\Helpers\logger;
use function Codefy\Framework\Helpers\storage_path;
use function Codefy\Framework\Helpers\view;
use function file_exists;
use function get_class;
use function is_string;
use function parse_str;
use function Qubus\Error\Helpers\is_error;
use function Qubus\Security\Helpers\esc_html;
use function Qubus\Security\Helpers\esc_html__;
use function Qubus\Security\Helpers\t__;
use function Qubus\Support\Helpers\is_false__;
use function sprintf;
use function strlen;
use function time;
use function unlink;

final class AdminUserController extends BaseController
{
    /**
     * @param ServerRequest $request
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
    public function userCreate(ServerRequest $request): ResponseInterface
    {
        if (false === current_user_can(perm: 'create:users')) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Access denied.', domain: 'devflow'),
            );
            return $this->redirect(admin_url());
        }

        if (strlen($request->get('pass')) < config()->integer(key: 'cms.password_length')) {
            Devflow::$PHP->flash->error(
                message: sprintf(
                    esc_html__(string: 'Passwords cannot be less than %s characters.', domain: 'devflow'),
                    config()->integer(key: 'cms.password_length')
                ),
            );
            $this->redirect($request->getHeaderLine(name: 'Referer'));
        }

        try {
            /** @var User $user */
            $user = get_user_by(field: 'email', value: $request->get('email'));

            if (is_false__($user)) {
                $update = false;
                $userLogin = $request->get('login');
                $extra = ['pass' => $request->get('pass')];
            } else {
                $update = true;
                $userLogin = $user->login;
                $extra = ['pass' => $request->get('pass'), 'login' => $userLogin];
            }

            if (empty($userLogin)) {
                Devflow::$PHP->flash->error(
                    message: t__(msgid: 'Username cannot be empty or null.', domain: 'devflow'),
                );
                return $this->redirect($request->getHeaderLine(name: 'Referer'));
            }
        } catch (
            NotFoundExceptionInterface |
            ContainerExceptionInterface |
            Exception $e
        ) {
            logger(level: 'error', message: $e->getMessage());
            Devflow::$PHP->flash->error(
                t__(msgid: 'User check exception occurred and was logged.', domain: 'devflow')
            );
            return $this->redirect($request->getHeaderLine(name: 'Referer'));
        }

        try {
            $arrayMerge = array_merge($extra, $request->getParsedBody());
            if ($update) {
                $userId = cms_update_user($arrayMerge);
            } else {
                $userId = cms_insert_user($arrayMerge);
            }

            if (is_error($userId)) {
                Devflow::$PHP->flash->error(
                    message: $userId->getMessage(),
                );
                return $this->redirect($request->getHeaderLine(name: 'Referer'));
            }

            /** @var User $newUser */
            $newUser = Devflow::$PHP->make(name: User::class);
            $newUser->id = $userId;
            $newUser->setRole($request->get('role'));

            if ($request->get('sendemail') === 1) {
                queue_new_user_email($userId, $request->get('pass'));
            }

            Devflow::$PHP->flash->success(
                message: Devflow::$PHP->flash->notice(num: 201),
            );

            $request->withAttribute('USER_BODY', $request->getParsedBody());

            return $this->redirect(admin_url("user/$userId/"));
        } catch (
            CommandPropertyNotFoundException |
            ContainerExceptionInterface |
            UnresolvableQueryHandlerException |
            TypeException |
            Exception $e
        ) {
            logger(
                level: 'error',
                message: $e->getMessage(),
                context: ['code' => $e->getCode(), 'exception' => get_class($e)]
            );
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Insertion exception occurred and was logged.', domain: 'devflow')
            );
        }

        return $this->redirect($request->getHeaderLine(name: 'Referer'));
    }

    /**
     * @param ServerRequest $request
     * @return ResponseInterface
     * @throws CommandPropertyNotFoundException
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws UnresolvableQueryHandlerException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function userCreateView(ServerRequest $request): ResponseInterface
    {
        if (false === current_user_can(perm: 'create:users')) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Access denied.', domain: 'devflow'),
            );
            return $this->redirect(admin_url());
        }

        return view(
            template: 'framework::backend/admin/user/create',
            data: [
                'title' => t__(msgid: 'Create User', domain: 'devflow'),
                'request' => $request->getAttribute('USER_BODY')
            ]
        );
    }

    /**
     * @return ResponseInterface
     * @throws CommandPropertyNotFoundException
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws UnresolvableQueryHandlerException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function users(): ResponseInterface
    {
        if (false === current_user_can(perm: 'manage:users')) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Access denied.', domain: 'devflow'),
            );
            return $this->redirect(admin_url());
        }

        try {
            $users = get_all_users();

            return view(
                template: 'framework::backend/admin/user/index',
                data: [
                    'title' => esc_html__(string: 'Users', domain: 'devflow'),
                    'users' => $users,
                ]
            );
        } catch (UnresolvableQueryHandlerException | ReflectionException $e) {
            logger(level: 'error', message: $e->getMessage());
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Query exception occurred and was logged.', domain: 'devflow')
            );
        }

        return JsonResponseFactory::create(data: t__(msgid: 'Users error', domain: 'devflow'), status: 404);
    }

    /**
     * @param ServerRequest $request
     * @param string $userId
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
    public function userChange(ServerRequest $request, string $userId): ResponseInterface
    {
        if (false === current_user_can(perm: 'update:users')) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Permission denied.', domain: 'devflow')
            );
            return $this->redirect(admin_url());
        }

        /**
         * Action triggered before user record is updated.
         */
        Action::getInstance()->doAction('pre_update_user', $userId);

        $dataArrayMerge = array_merge(['id' => $userId], $request->getParsedBody());

        try {
            $userId = cms_update_user($dataArrayMerge);

            if (is_error($userId)) {
                Devflow::$PHP->flash->error(
                    message: $userId->getMessage(),
                );
                return $this->redirect($request->getHeaderLine(name: 'Referer'));
            }

            /** @var User $newUser */
            $newUser = Devflow::$PHP->make(name: User::class);
            $newUser->id = $userId;
            $newUser->setRole($request->get('role'));

            Devflow::$PHP->flash->success(
                message: Devflow::$PHP->flash->notice(num: 200),
            );
        } catch (
            CommandPropertyNotFoundException |
            ContainerExceptionInterface |
            UnresolvableQueryHandlerException |
            TypeException |
            Exception $e
        ) {
            logger(level: 'error', message: $e->getMessage());
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'User change exception occurred and was logged.', domain: 'devflow')
            );
        }

        return $this->redirect($request->getHeaderLine(name: 'Referer'));
    }

    /**
     * @param string $userId
     * @return ResponseInterface
     * @throws CommandPropertyNotFoundException
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws UnresolvableQueryHandlerException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function userView(string $userId): ResponseInterface
    {
        if (false === current_user_can(perm: 'update:content')) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Access denied.', domain: 'devflow')
            );
            return $this->redirect(admin_url());
        }

        try {
            /** @var User $user */
            $user = get_user_by('id', $userId);

            return view(
                template: 'framework::backend/admin/user/view',
                data: [
                    'title' => t__(msgid: 'View User', domain: 'devflow'),
                    'user' => $user,
                ]
            );
        } catch (
            CommandPropertyNotFoundException |
            ContainerExceptionInterface |
            InvalidArgumentException |
            UnresolvableQueryHandlerException |
            TypeException |
            ReflectionException $e
        ) {
            logger(level: 'error', message: $e->getMessage());
        }

        return JsonResponseFactory::create(
            data: t__(msgid: 'The user does not exist.', domain: 'devflow'),
            status: 404
        );
    }

    /**
     * @param ServerRequest $request
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
    public function userDelete(ServerRequest $request): ResponseInterface
    {
        if (false === current_user_can(perm: 'delete:users')) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Access denied.', domain: 'devflow')
            );
            return $this->redirect(admin_url());
        }

        try {
            $oldUser = get_user_by(field: 'id', value: $request->getParsedBody()['user_id']);
            $assignId = (string) $request->getParsedBody()['assign_id'];

            remove_user_from_site(
                userId: $request->getParsedBody()['user_id'],
                params: [
                    'site_id' => get_current_site_id(),
                    'assign_id' => $assignId,
                    'role' => $oldUser->role
                ]
            );

            Devflow::$PHP->flash->success(
                message: Devflow::$PHP->flash->notice(num: 201),
            );
        } catch (
            CommandPropertyNotFoundException |
            ContainerExceptionInterface |
            TypeException |
            Exception |
            ReflectionException $e
        ) {
            logger('error', $e->getMessage());
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'A removal exception occurred and was logged.', domain: 'devflow')
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
                message: t__(msgid: 'Access denied.', domain: 'devflow')
            );
        }

        $user = ask(new FindUserByIdQuery(['id' => UserId::fromNative($request->getParsedBody()['id'])]));

        $json = [
            'input#fname' => esc_html($user->fname), 'input#lname' => esc_html($user->lname),
            'input#email' => esc_html($user->email)
        ];
        return json_encode($json);
    }

    /**
     * @param ServerRequest $request
     * @param string $userId
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
    public function userResetPassword(ServerRequest $request, string $userId): ResponseInterface
    {
        if (false === current_user_can(perm: 'update:users')) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Access denied.', domain: 'devflow')
            );
        }

        try {
            $user = get_user_by(field: 'id', value: $userId);
            if (is_false__($user)) {
                Devflow::$PHP->flash->error(t__(msgid: 'User not found.', domain: 'devflow'));
            }

            $_userId = reset_password($userId);
            if (is_string($_userId) && !is_false__($_userId)) {
                Devflow::$PHP->flash->success(
                    sprintf(
                        t__(msgid: 'Password successfully updated for <strong>%s</strong>.', domain: 'devflow'),
                        get_name($_userId)
                    ),
                );
            }
        } catch (
            NotFoundExceptionInterface |
            ContainerExceptionInterface |
            InvalidArgumentException |
            \Qubus\Exception\Exception |
            ReflectionException |
            CommandCouldNotBeHandledException |
            UnresolvableCommandHandlerException |
            EnvironmentIsBrokenException $e
        ) {
            logger(level: 'error', message: $e->getMessage());
            Devflow::$PHP->flash->error(
                t__(msgid: 'Reset password exception occurred and was logged.', domain: 'devflow')
            );
        }
        return $this->redirect($request->getHeaderLine(name: 'Referer'));
    }

    /**
     * @param ServerRequest $request
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function userProfile(ServerRequest $request): ResponseInterface
    {
        if (!is_user_logged_in()) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Access denied.', domain: 'devflow')
            );
        }

        if ($request->getMethod() === 'POST') {
            try {
                $userId = cms_update_user($request->getParsedBody());

                if (is_error($userId)) {
                    Devflow::$PHP->flash->error(t__(msgid: 'An update error occurred.', domain: 'devflow'));

                    return $this->redirect(admin_url('user/profile/'));
                }

                /** @var User $user */
                $user = Devflow::$PHP->make(name: User::class);
                $user->id = $userId;
                $user->setRole($request->get('role'));

                Devflow::$PHP->flash->success(message: Devflow::$PHP->flash->notice(num: 200));
            } catch (
                CommandPropertyNotFoundException |
                UnresolvableQueryHandlerException |
                NotFoundExceptionInterface |
                ContainerExceptionInterface |
                TypeException |
                \Qubus\Exception\Exception |
                ReflectionException $e
            ) {
                logger(level: 'error', message: $e->getMessage());
                Devflow::$PHP->flash->error(
                    t__(msgid: 'An update exception occurred and was logged.', domain: 'devflow')
                );
            }
        }

        return view(
            template: 'framework::backend/admin/user/profile',
            data: [
                'title' => t__(msgid: 'User Profile', domain: 'devflow'),
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
                message: t__(msgid: 'Access denied.', domain: 'devflow')
            );
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

            CookiesResponse::set(
                response: $response,
                setCookieCollection: $cookieFactory->make(
                    name: config()->string(key: 'auth.cookie_name', default: 'USERSESSID'),
                    value: Crypto::encrypt(
                        plaintext: get_user_value(id: $userId, field: 'token'),
                        key: Key::loadFromAsciiSafeString(config()->string(key: 'app.crypto_key'))
                    ),
                    maxAge: (int) get_option(key: 'cookieexpire', default: 172800) + time()
                )
            );

            Devflow::$PHP->flash->success(
                message: t__(msgid: 'User switching was successful.', domain: 'devflow')
            );
        } catch (
            NotFoundExceptionInterface |
            ContainerExceptionInterface |
            \Qubus\Exception\Exception |
            ReflectionException |
            Exception $e
        ) {
            logger(level: 'error', message: $e->getMessage());
            Devflow::$PHP->flash->error(
                t__(msgid: 'User switching exception occurred and was logged.', domain: 'devflow')
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
                message: t__(msgid: 'Access denied.', domain: 'devflow')
            );
        }

        try {
            $cookies = NativePhpCookies::factory();

            if ($cookies->get('USERCOOKIEID') === null || $cookies->get('USERCOOKIEID') === '') {
                Devflow::$PHP->flash->error(
                    message: t__(msgid: 'Cookie is not properly set for user switching.', domain: 'devflow')
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
                'token' => get_user_value($userId, 'token'),
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

            CookiesResponse::set(
                response: $response,
                setCookieCollection: $cookieFactory->make(
                    name: config()->string(key: 'auth.cookie_name', default: 'USERSESSID'),
                    value: Crypto::encrypt(
                        plaintext: get_user_value($userId, 'token'),
                        key: Key::loadFromAsciiSafeString(config()->string(key: 'app.crypto_key'))
                    ),
                    maxAge: (int) get_option(key: 'cookieexpire', default: 172800) + time()
                )
            );

            Devflow::$PHP->flash->success(
                message: t__(msgid: 'Switching back to previous user session was successful.', domain: 'devflow')
            );
        } catch (
            \Qubus\Exception\Exception |
            ReflectionException |
            NotFoundExceptionInterface |
            ContainerExceptionInterface |
            Exception $e
        ) {
            logger(level: 'error', message: $e->getMessage());
            Devflow::$PHP->flash->error(
                t__(msgid: 'User switch back exception occurred and was logged.', domain: 'devflow')
            );
        }

        return $this->redirect($request->getHeaderLine(name: 'Referer'));
    }
}
