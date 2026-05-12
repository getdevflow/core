<?php

declare(strict_types=1);

namespace App\Shared\Helpers;

use App\Application\Devflow;
use App\Domain\User\Model\User;
use App\Infrastructure\Persistence\Cache\UserCachePsr16;
use Qubus\Expressive\Database;
use App\Infrastructure\Services\NativePhpCookies;
use Codefy\Framework\Auth\Rbac\Rbac;
use Codefy\Framework\Support\Password;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Exception\Http\Client\NotFoundException;
use Qubus\Http\ServerRequest;
use Qubus\Http\Session\SessionException;
use ReflectionException;

use function Codefy\Framework\Helpers\app;
use function Codefy\Framework\Helpers\config;
use function Codefy\Framework\Helpers\gate;
use function Codefy\Framework\Helpers\logger;
use function Codefy\Framework\Helpers\storage_path;
use function Codefy\Framework\Helpers\trans;
use function file_exists;
use function filter_var;
use function parse_str;
use function Qubus\Routing\Helpers\redirect;
use function Qubus\Security\Helpers\__observer;
use function Qubus\Security\Helpers\esc_html;
use function Qubus\Support\Helpers\is_false__;
use function Qubus\Support\Helpers\is_null__;
use function sprintf;
use function time;
use function unlink;

/**
 * @file core/Shared/Helpers/auth.php
 * @return array
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_roles(): array
{
    /** @var User $user */
    $user = cms_get_current_user();

    $result = [];
    foreach ((array) $user->role as $roleName) {
        /** @var Rbac $rbac */
        $rbac = app(name: Rbac::class);
        if ($role = $rbac->getRole($roleName)) {
            $result[$roleName] = $role;
        }
    }
    return $result;
}

/**
 * Checks if current user has specified permission or not.
 *
 * @file core/Shared/Helpers/auth.php
 * @param string $perm Permission to check for.
 * @param array $ruleParams (Optional) Other parameters to use for checking
 *                          based on a rule.
 * @return bool Return true if permission matches or false otherwise.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 */
function current_user_can(string $perm, array $ruleParams = []): bool
{
    $currentUser = cms_get_current_user();
    if (empty($currentUser) || is_false__($currentUser)) {
        return false;
    }

    if(is_super_admin($currentUser->id)) {
        return true;
    }

    return gate(permission: $perm, ruleParams: $ruleParams);
}

/**
 * Checks if a visitor is logged in or not.
 *
 * @file core/Shared/Helpers/auth.php
 * @return bool
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function is_user_logged_in(): bool
{
    if (!isset($_COOKIE['USERCOOKIEID'])) {
        return false;
    }

    $cookies = NativePhpCookies::factory();

    if(false === $currentUser = cms_get_current_user()) {
        return false;
    }

    $user = get_user_by(field: 'token', value: $currentUser->token);
    return false !== $user && $cookies->verifySecureCookie(key: 'USERCOOKIEID') && gate()->isLoggedIn();
}

/**
 * Checks if logged-in user can access menu, tab, or screen.
 *
 * @file core/Shared/Helpers/auth.php
 * @param string $perm Permission to check for.
 * @return string HTML style.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 */
function ae(string $perm): string
{
    if (!current_user_can($perm)) {
        return ' style="display:none !important;"';
    }

    return '';
}

/**
 * Logs a user in after the login information has checked out.
 *
 * @file core/Shared/Helpers/auth.php
 * @param string $login User's username or email address.
 * @param string $password User's password.
 * @param string $rememberme Whether to remember the user.
 * @return ResponseInterface
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws SessionException
 */
function cms_authenticate(string $login, string $password, string $rememberme): ResponseInterface
{
    $dfdb = dfdb();

    $request = new ServerRequest();

    $sql = "SELECT u.*"
    . " FROM {$dfdb->basePrefix}user u"
    . " WHERE u.user_login = ?"
    . " OR u.user_email = ?";

    $user = $dfdb->getRow($dfdb->prepare($sql, [$login, $login]), Database::ARRAY_A);

    if (is_null__($user)) {
        Devflow::$PHP->flash->error(
            trans_html(
                'Sorry, there was an error.',
                
            ),
        );

        return redirect($request->getHeaderLine('Referer'));
    }

    /**
     * Filters the authentication cookie.
     *
     * @file core/Shared/Helpers/auth.php
     * @param array $user User data array.
     * @param string $rememberme Whether to remember the user.
     */
    __observer()->filter->applyFilter('cms.auth.cookie', $user, $rememberme);

    $redirectTo = __observer()->filter->applyFilter(
        'authenticate.redirect.to',
        $request->getParsedBody()['redirect_to'] ?? admin_url()
    );

    Devflow::$PHP->flash->success(
        sprintf(
            trans(
                'Login was successful. Welcome <strong>%s</strong> to the admin dashboard.',
            ),
            get_name(esc_html($user['user_id']))
        ),
    );

    return redirect($redirectTo);
}

/**
 * Checks a user's login information.
 *
 * @file core/Shared/Helpers/auth.php
 * @param string $login User's username or email address.
 * @param string $password User's password.
 * @param string $rememberme Whether to remember the user.
 * @return string|ResponseInterface Returns credentials if valid, null or false otherwise.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws SessionException
 */
function cms_authenticate_user(string $login, string $password, string $rememberme): string|ResponseInterface
{
    $request = new ServerRequest();

    if ($login === '' || $password === '') {
        if (empty($login)) {
            Devflow::$PHP->flash->error(
                trans(
                    '<strong>ERROR</strong>: The username/email field is empty.',
                ),
            );
            return redirect($request->getHeaderLine(name: 'Referer'));
        }

        if ($password === '') {
            Devflow::$PHP->flash->error(
                trans(
                    '<strong>ERROR</strong>: The password field is empty.',
                    
                ),
            );
            return redirect($request->getHeaderLine(name: 'Referer'));
        }
    }

    if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
        $user = get_user_by('email', $login);

        if (is_false__($user)) {
            Devflow::$PHP->flash->error(
                trans(
                    '<strong>ERROR</strong>: Invalid email address.',
                    
                ),
            );
            return redirect($request->getHeaderLine(name: 'Referer'));
        }
    } else {
        $user = get_user_by('login', $login);

        if (is_false__($user)) {
            Devflow::$PHP->flash->error(
                trans(
                    '<strong>ERROR</strong>: Invalid username.',
                    
                ),
            );
            return redirect($request->getHeaderLine(name: 'Referer'));
        }
    }

    if (!Password::verify($password, $user->pass)) {
        Devflow::$PHP->flash->error(
            trans(
                '<strong>ERROR</strong>: The password you entered is incorrect.',
                
            ),
        );
        return redirect($request->getHeaderLine(name: 'Referer'));
    }

    UserCachePsr16::update($user);

    /**
     * Filters log in details.
     *
     * @file core/Shared/Helpers/auth.php
     * @param string $login User's username or email address.
     * @param string $password User's password.
     * @param string $rememberme Whether to remember the user.
     */
    return __observer()->filter->applyFilter('cms.authenticate.user', $login, $password, $rememberme);
}

/**
 * Sets auth cookie.
 *
 * @file core/Shared/Helpers/auth.php
 * @param array $user User data array.
 * @param string $rememberme Should user be remembered for a length of time?
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws ReflectionException
 */
function cms_set_auth_cookie(array $user, string $rememberme = ''): void
{
    $cookies = NativePhpCookies::factory();

    if ($rememberme === 'yes') {
        /**
         * Ensure the browser will continue to send the cookie until it expires.
         *
         * @file core/Shared/Helpers/auth.php
         */
        $expire = __observer()->filter->applyFilter(
            'auth.cookie.expiration',
            option()->read('cookieexpire', 172800)
        );
    } else {
        /**
         * Ensure the browser will continue to send the cookie until it expires.
         *
         * @file core/Shared/Helpers/auth.php
         */
        $expire = __observer()->filter->applyFilter(
            'auth.cookie.expiration',
            config('cookies.lifetime') ?? 86400
        );
    }

    $authCookie = [
        'key' => 'USERCOOKIEID',
        'id' => esc_html($user['user_id']),
        'token' => esc_html($user['user_token']),
        'remember' => ($rememberme == 'yes' ? 'yes' : 'no'),
        'exp' => (int) $expire + time()
    ];

    /**
     * Fires immediately before the secure authentication cookie is set.
     *
     * @file core/Shared/Helpers/auth.php
     * @param array $authCookie Authentication cookie.
     * @param int   $expire  Duration in seconds the authentication cookie should be valid.
     */
    __observer()->action->doAction('set_auth_cookie', $authCookie, $expire);

    $cookies->setSecureCookie($authCookie);
}

/**
 * Removes all cookies associated with authentication.
 *
 * @file core/Shared/Helpers/auth.php
 * @throws Exception
 */
function cms_clear_auth_cookie(): void
{
    $cookies = NativePhpCookies::factory();
    /**
     * Fires just before the authentication cookies are cleared.
     *
     * @file core/Shared/Helpers/auth.php
     */
    __observer()->action->doAction('clear_auth_cookie');

    $vars1 = [];
    parse_str($cookies->get('USERCOOKIEID'), $vars1);
    /**
     * Checks to see if the cookie exists on the server.
     * If it exists, we need to delete it.
     */
    $file1 = storage_path(path: 'app/cookies/cookie.' . $vars1['data']);
    try {
        if (file_exists($file1)) {
            unlink($file1);
        }
    } catch (NotFoundException $e) {
        logger(
            'error',
            sprintf(
                'FILESTATE[%s]: File not found: %s',
                $e->getCode(),
                $e->getMessage()
            )
        );
    }

    if (isset($_COOKIE['SWITCH_USERBACK'])) {
        $vars2 = [];
        parse_str($cookies->get('SWITCH_USERBACK'), $vars2);
        /**
         * Checks to see if the cookie exists on the server.
         * If it exists, we need to delete it.
         */
        $file2 = storage_path(path: 'app/cookies/cookie.' . $vars2['data']);
        if (file_exists($file2)) {
            @unlink($file2);
        }

        $cookies->remove(key: 'SWITCH_USERBACK');
    }

    /**
     * After the cookie is removed from the server,
     * we know need to remove it from the browser and
     * redirect the user to the login page.
     */
    $cookies->remove(key: 'USERCOOKIEID');
}

/**
 * Shows error messages on login form.
 *
 * @file core/Shared/Helpers/auth.php
 * @throws Exception
 */
function login_form_show_message(): void
{
    echo __observer()->filter->applyFilter('login.form.show.message', Devflow::$PHP->flash->display());
}

/**
 * Retrieves data from a secure cookie.
 *
 * @file core/Shared/Helpers/auth.php
 * @param string $key COOKIE key.
 * @return false|array|object Cookie data or false.
 */
function get_secure_cookie_data(string $key): false|array|object
{
    $cookies = NativePhpCookies::factory();

    if ($cookies->verifySecureCookie($key)) {
        return $cookies->getSecureCookie($key);
    }
    return false;
}

/**
 * @throws TypeException
 */
function get_user_roles(): array
{
    return config()->array(key: 'rbac.roles');
}
