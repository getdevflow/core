<?php

declare(strict_types=1);

namespace App\Shared\Helpers;

use App\Application\Devflow;
use App\Domain\User\Model\User;
use App\Infrastructure\Persistence\Cache\UserCachePsr16;
use Qubus\EventDispatcher\ActionFilter\Filter;
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
use Qubus\Http\ServerRequest;
use Qubus\Http\Session\SessionException;
use ReflectionException;

use function Codefy\Framework\Helpers\app;
use function Codefy\Framework\Helpers\config;
use function Codefy\Framework\Helpers\gate;
use function Codefy\Framework\Helpers\logger;
use function Codefy\Framework\Helpers\trans;
use function Codefy\Framework\Helpers\trans_html;
use function filter_var;
use function is_string;
use function parse_url;
use function preg_match;
use function Qubus\Routing\Helpers\redirect;
use function Qubus\Security\Helpers\__observer;
use function Qubus\Security\Helpers\esc_html;
use function Qubus\Support\Helpers\is_false__;
use function Qubus\Support\Helpers\is_null__;
use function rawurldecode;
use function sprintf;
use function strtolower;
use function time;
use function trim;

use const FILTER_VALIDATE_EMAIL;

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

    if (false === $currentUser = cms_get_current_user()) {
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
 * Returns a safe local redirect destination.
 *
 * Relative destinations must be root-relative. Absolute destinations must use
 * HTTP(S) and match the trusted application's scheme, host, and port.
 *
 * @param mixed $candidate Untrusted redirect destination.
 * @param string $fallback Trusted application-controlled fallback URL.
 * @return string
 */
function cms_safe_redirect_url(mixed $candidate, string $fallback): string
{
    if (!is_string($candidate)) {
        return $fallback;
    }

    $candidate = trim($candidate);

    if (
        $candidate === ''
        || preg_match('/[\x00-\x1F\x7F]/', $candidate) === 1
        || str_contains($candidate, '\\')
    ) {
        return $fallback;
    }

    /*
     * Decode once for structural checks so encoded backslashes and an encoded
     * scheme-relative prefix cannot bypass the local-path rules.
     */
    $decodedCandidate = rawurldecode($candidate);

    if (
        str_contains($decodedCandidate, '\\')
        || str_starts_with($decodedCandidate, '//')
    ) {
        return $fallback;
    }

    $candidateParts = parse_url($candidate);

    if ($candidateParts === false) {
        return $fallback;
    }

    $candidateScheme = $candidateParts['scheme'] ?? null;
    $candidateHost = $candidateParts['host'] ?? null;

    /*
     * A local destination must begin with exactly one slash. This rejects
     * scheme-relative URLs, bare hostnames, and ambiguous relative paths.
     */
    if ($candidateScheme === null && $candidateHost === null) {
        return str_starts_with($candidate, '/') && !str_starts_with($candidate, '//')
            ? $candidate
            : $fallback;
    }

    if (!is_string($candidateScheme) || !is_string($candidateHost)) {
        return $fallback;
    }

    $candidateScheme = strtolower($candidateScheme);

    if ($candidateScheme !== 'http' && $candidateScheme !== 'https') {
        return $fallback;
    }

    $trustedParts = parse_url($fallback);

    if ($trustedParts === false) {
        return $fallback;
    }

    $trustedScheme = $trustedParts['scheme'] ?? null;
    $trustedHost = $trustedParts['host'] ?? null;

    if (!is_string($trustedScheme) || !is_string($trustedHost)) {
        return $fallback;
    }

    $candidatePort = $candidateParts['port'] ?? ($candidateScheme === 'https' ? 443 : 80);

    $trustedScheme = strtolower($trustedScheme);
    $trustedPort = $trustedParts['port'] ?? ($trustedScheme === 'https' ? 443 : 80);

    if (
        $candidateScheme !== $trustedScheme
        || strtolower($candidateHost) !== strtolower($trustedHost)
        || $candidatePort !== $trustedPort
    ) {
        return $fallback;
    }

    return $candidate;
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

        return redirect(
            cms_safe_redirect_url(
                candidate: $request->getHeaderLine('Referer'),
                fallback: admin_url()
            )
        );
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

    $redirectTo = cms_safe_redirect_url(
        candidate: $redirectTo,
        fallback: admin_url()
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
            return redirect(
                cms_safe_redirect_url(
                    candidate: $request->getHeaderLine(name: 'Referer'),
                    fallback: admin_url()
                )
            );
        }

        if ($password === '') {
            Devflow::$PHP->flash->error(
                trans(
                    '<strong>ERROR</strong>: The password field is empty.',
                ),
            );
            return redirect(
                cms_safe_redirect_url(
                    candidate: $request->getHeaderLine(name: 'Referer'),
                    fallback: admin_url()
                )
            );
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
            return redirect(
                cms_safe_redirect_url(
                    candidate: $request->getHeaderLine(name: 'Referer'),
                    fallback: admin_url()
                )
            );
        }
    } else {
        $user = get_user_by('login', $login);

        if (is_false__($user)) {
            Devflow::$PHP->flash->error(
                trans(
                    '<strong>ERROR</strong>: Invalid username.',

                ),
            );
            return redirect(
                cms_safe_redirect_url(
                    candidate: $request->getHeaderLine(name: 'Referer'),
                    fallback: admin_url()
                )
            );
        }
    }

    if (!Password::verify($password, $user->pass)) {
        Devflow::$PHP->flash->error(
            trans(
                '<strong>ERROR</strong>: The password you entered is incorrect.',

            ),
        );
        return redirect(
            cms_safe_redirect_url(
                candidate: $request->getHeaderLine(name: 'Referer'),
                fallback: admin_url()
            )
        );
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

    $cookies->deleteSecureCookie('USERCOOKIEID');

    if (isset($_COOKIE['SWITCH_USERBACK'])) {
        $cookies->deleteSecureCookie('SWITCH_USERBACK');
    }
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
 * @throws TypeException
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
 * Retrieve a list of system defined user roles.
 *
 * @file core/Shared/Helpers/user.php
 * @param string|null $active
 * @return void
 * @throws Exception
 * @throws ReflectionException
 * @throws TypeException
 */
function get_system_roles(?string $active = null): void
{
    $roles = Filter::getInstance()->applyFilter('system.roles', config()->array(key: 'rbac.roles'));

    foreach ($roles as $role => $permission) {
        echo '<option value="' . esc_html($role) . '"' . selected($active, esc_html($role), false) . '>' .
            esc_html($role) .
            '</option>';
    }
}

/**
 * @return array
 * @throws Exception
 * @throws ReflectionException
 * @throws TypeException
 */
function get_user_roles(): array
{
    return Filter::getInstance()->applyFilter('user.roles', config()->array(key: 'rbac.roles'));
}
