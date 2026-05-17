<?php

declare(strict_types=1);

namespace App\Shared\Helpers;

use App\Application\Devflow;
use App\Domain\User\Command\UpdateUserPasswordCommand;
use App\Domain\User\Command\CreateUserCommand;
use App\Domain\User\Command\UpdateUserCommand;
use App\Domain\User\Model\User;
use App\Domain\User\Query\FindMultisiteUniqueUsersQuery;
use App\Domain\User\Query\FindUsersQuery;
use App\Domain\User\UserError;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\Username;
use App\Domain\User\ValueObject\UserToken;
use App\Infrastructure\Persistence\Cache\UserCachePsr16;
use App\Infrastructure\Services\Queue\EmailChangeNotification;
use App\Shared\Services\SimpleCacheObjectCacheFactory;
use Qubus\Expressive\Database;
use App\Infrastructure\Services\AttributesFactory;
use App\Infrastructure\Services\NativePhpCookies;
use App\Infrastructure\Services\User\UserAttributeBag;
use App\Shared\Services\DateTime;
use App\Shared\Services\Sanitizer;
use App\Shared\Services\Utils;
use Codefy\CommandBus\Exceptions\CommandCouldNotBeHandledException;
use Codefy\CommandBus\Exceptions\CommandPropertyNotFoundException;
use Codefy\CommandBus\Exceptions\UnresolvableCommandHandlerException;
use Codefy\Framework\Support\Password;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Error\Error;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Support\DateTime\QubusDateTimeImmutable;
use Qubus\ValueObjects\StringLiteral\StringLiteral;
use Qubus\ValueObjects\Web\EmailAddress;
use ReflectionException;

use function array_map;
use function Codefy\Framework\Helpers\app;
use function Codefy\Framework\Helpers\ask;
use function Codefy\Framework\Helpers\command;
use function Codefy\Framework\Helpers\config;
use function Codefy\Framework\Helpers\logger;
use function Codefy\Framework\Helpers\queue;
use function Codefy\Framework\Helpers\trans;
use function Codefy\Framework\Helpers\trans_html;
use function in_array;
use function Qubus\Security\Helpers\__observer;
use function Qubus\Security\Helpers\esc_html;
use function Qubus\Security\Helpers\trim__;
use function Qubus\Security\Helpers\unslash;
use function Qubus\Support\Helpers\concat_ws;
use function Qubus\Support\Helpers\is_false__;
use function Qubus\Support\Helpers\is_null__;
use function sprintf;
use function strcasecmp;
use function strtolower;

/**
 * Retrieves all users.
 *
 * @file core/Shared/Helpers/user.php
 * @return mixed
 * @throws ReflectionException
 * @throws UnresolvableQueryHandlerException
 */
function get_all_users(): mixed
{
    return ask(new FindMultisiteUniqueUsersQuery());
}

/**
 * Print a dropdown list of users.
 *
 * @file core/Shared/Helpers/user.php
 * @param string|null $active If working with active record, it will be the user's id.
 * @return void Dropdown list of users.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_users_dropdown(?string $active = null): void
{
    $dfdb = dfdb();

    $sql = "SELECT DISTINCT user_id FROM {$dfdb->basePrefix}user WHERE user_id NOT IN(?)";

    $users = $dfdb->getResults(query: $dfdb->prepare($sql, [$active]), output: Database::ARRAY_A);

    foreach ($users as $user) {
        echo '<option value="' . esc_html($user['user_id'])
        . '"' . selected(esc_html($user['user_id']), $active, false) . '>'
        . get_name(esc_html($user['user_id'])) . '</option>';
    }
}

/**
 * @throws ContainerExceptionInterface
 * @throws ReflectionException
 * @throws NotFoundExceptionInterface
 * @throws Exception
 */
function user_lookup(?string $active = null): void
{
    $dfdb = dfdb();

    $sql = "SELECT
    u.user_id,
    u.user_login,
    u.user_fname,
    u.user_lname
FROM {$dfdb->basePrefix}user u
WHERE NOT EXISTS (
    SELECT 1
    FROM {$dfdb->basePrefix}site_user su
    WHERE su.user_id = u.user_id
      AND su.site_id = ?
)";

    $users = $dfdb->getResults(query: $dfdb->prepare($sql, [get_current_site_id()]), output: Database::ARRAY_A);
    foreach ($users as $user) {
        echo '<option value="' . esc_html($user['user_id'])
        . '"' . selected(esc_html($user['user_id']), $active, false) . '>'
        . get_name(esc_html($user['user_id'])) . '</option>';
    }
}

/**
 * Get the current user's ID
 *
 * @file core/Shared/Helpers/user.php
 * @return string The current user's ID, or '' if no user is logged in.
 * @throws ReflectionException
 * @throws Exception
 */
function get_current_user_id(): string
{
    $verify = NativePhpCookies::factory()->verifySecureCookie(key: 'USERCOOKIEID');
    if (is_false__($verify)) {
        return '';
    }

    $cookie = get_secure_cookie_data(key: 'USERCOOKIEID');
    if (is_false__($cookie)) {
        return '';
    }

    if (null === $user = \Codefy\Framework\Helpers\user()) {
        return '';
    }

    if ($cookie->id !== $user->id) {
        return '';
    }

    return esc_html($cookie->id);
}

/**
 * Returns object of data for current user.
 *
 * @file core/Shared/Helpers/user.php
 * @return User|false
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function cms_get_current_user(): false|User
{
    if (empty(get_current_user_id())) {
        return false;
    }
    return get_userdata(get_current_user_id());
}

/**
 * Retrieve user info by a given field from the user's table.
 *
 * @file core/Shared/Helpers/user.php
 * @param string $field The field to retrieve the user with.
 * @param int|string $value A value for $field (id, login or token).
 * @return User|false User array on success, false otherwise.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_user_by(string $field, mixed $value): User|false
{
    /** @var User $user */
    $user = app(name: User::class);
    $userdata = $user->findBy($field, $value);

    if (is_false__($userdata)) {
        return false;
    }

    return $userdata;
}

/**
 * Retrieve user info by user_id.
 *
 * @file core/Shared/Helpers/user.php
 * @param mixed $userId User's id.
 * @return User|false User array on success, false on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_userdata(string $userId): false|User
{
    return get_user_by(field: 'id', value: $userId);
}

/**
 * Returns the name of a particular user.
 *
 * Uses `user.name` filter.
 *
 * @file core/Shared/Helpers/user.php
 * @param string $id User ID.
 * @param bool $reverse Reverse order (true = Last Name, First Name).
 * @return string User's name.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_name(string $id, bool $reverse = false): string
{
    /** @var User $name */
    $name = get_user_by(field: 'id', value: $id);

    if ($reverse) {
        $_name = $name->fname . ' ' . $name->lname;
    } else {
        $_name = $name->lname . ', ' . $name->fname;
    }

    return __observer()->filter->applyFilter('user.name', $_name);
}

/**
 * Shows selected user's initials instead of
 * his/her full name.
 *
 * Uses `user.initials` filter.
 *
 * @file core/Shared/Helpers/user.php
 * @param string $id User ID
 * @param int $initials Number of initials to show.
 * @return string User's initials.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_initials(string $id, int $initials = 2): string
{
    /** @var User $name */
    $name = get_user_by(field: 'id', value: $id);

    if ($initials === 2) {
        $_initials = mb_substr($name->fname, 0, 1, 'UTF-8')
        . '. ' . mb_substr($name->lname, 0, 1, 'UTF-8') . '.';
    } else {
        $_initials = $name->lname . ', ' . mb_substr($name->fname, 0, 1, 'UTF-8') . '.';
    }

    return __observer()->filter->applyFilter('user.initials', $_initials);
}

/**
 * Retrieve requested field from user meta table based on user's id.
 *
 * @file core/Shared/Helpers/user.php
 * @param string $id User ID.
 * @param string $field Data requested of particular user.
 * @return mixed
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_user_value(string $id, string $field): mixed
{
    $value = get_user_by(field: 'id', value: $id);
    return $value->{$field};
}

/**
 * Checks whether the given username exists.
 *
 * Uses `username.exists` filter.
 *
 * @file core/Shared/Helpers/user.php
 * @param string $username Username to check.
 * @return string|false The user's ID on success or false on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function username_exists(string $username): false|string
{
    /** @var User $user */
    $user = get_user_by(field: 'login', value: $username);
    if (is_false__($user)) {
        return false;
    }

    $userId = $user->id;

    /**
     * Filters whether the given username exists or not.
     *
     * @file core/Shared/Helpers/user.php
     * @param string|false $userId  The user's user_id on success or false on failure.
     * @param string    $username   Username to check.
     */
    return __observer()->filter->applyFilter('username.exists', $userId, $username);
}

/**
 * Checks whether the given email exists.
 *
 * Uses `email.exists` filter.
 *
 * @file core/Shared/Helpers/user.php
 * @param string $email Email to check.
 * @return string|false The user's ID on success or false on failure.
 * @throws Exception
 */
function email_exists(string $email): false|string
{
    $dfdb = dfdb();
    $prepare = "SELECT * FROM {$dfdb->basePrefix}user WHERE user_email = ?";

    if ($user = $dfdb->getRow($dfdb->prepare($prepare, [$email]))) {
        $userId = esc_html($user->user_id);
    } else {
        $userId = false;
    }

    /**
     * Filters whether the given email exists or not.
     *
     * @file core/Shared/Helpers/user.php
     * @param string|false $userId The user's user_id on success, and false on failure.
     * @param string       $email  Email to check.
     */
    return __observer()->filter->applyFilter('email.exists', $userId, $email);
}

/**
 * Retrieve a list of system defined user roles.
 *
 * @file core/Shared/Helpers/user.php
 * @param string|null $active
 * @return void
 * @throws TypeException
 * @throws Exception
 */
function get_system_roles(?string $active = null): void
{
    $roles = config()->array(key: 'rbac.roles');

    foreach ($roles as $role => $permission) {
        echo '<option value="' . esc_html($role) . '"' . selected($active, esc_html($role), false) . '>' .
        esc_html($role) .
        '</option>';
    }
}

/**
 * Retrieve a list of all users as dropdown options.
 *
 * @file core/Shared/Helpers/user.php
 * @param string|null $active
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws UnresolvableQueryHandlerException
 */
function get_users_dropdown_list(?string $active = null): void
{
    $users = ask(new FindUsersQuery());

    foreach ($users as $user) {
        echo '<option value="' . $user['id'] . '"' . selected($active, $user['id'], false) . '>'
        . get_name($user['id']) . '</option>';
    }
}

/**
 * Retrieve an attribute for specified user.
 *
 * @param string $userId
 * @param string $key
 * @param string|null $siteId
 * @param mixed|null $default
 * @return mixed
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_user_attribute(string $userId, string $key, ?string $siteId = null, mixed $default = null): mixed
{
    if (is_null__($siteId)) {
        $siteId = get_current_site_id();
    }
    return AttributesFactory::user()->get($siteId, $userId, $key, $default);
}

/**
 * @param string $userId
 * @param string $key
 * @param mixed $value
 * @param string|null $siteId
 * @return UserAttributeBag
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function update_user_attribute(string $userId, string $key, mixed $value, ?string $siteId = null): UserAttributeBag
{
    if (is_null__($siteId)) {
        $siteId = get_current_site_id();
    }

    return AttributesFactory::user()->set($siteId, $userId, $key, $value);
}

/**
 * Remove attribute from user_attribute.
 *
 * @param string $siteId
 * @param string $userId
 * @param string $key
 * @return UserAttributeBag
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 */
function delete_user_attribute(string $siteId, string $userId, string $key): UserAttributeBag
{
    return AttributesFactory::user()->remove($siteId, $userId, $key);
}

/**
 * @param string $siteId
 * @param string $userId
 * @return void
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 */
function delete_site_user_record(string $siteId, string $userId): void
{
    AttributesFactory::user()->delete($siteId, $userId);
}

/**
 * Retrieve user option that can be either per Site or global.
 *
 * If the user ID is not given, then the current user will be used instead. If
 * the user ID is given, then the user data will be retrieved. The filter for
 * the result, will also pass the original option name and finally the user id
 * as the third parameter.
 *
 * Uses `get.user.option.$option` filter.
 *
 * @file core/Shared/Helpers/user.php
 * @param string $option User option name.
 * @param string $userId User ID.
 * @return bool|string|int|array|null User option value on success or null on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_user_option(string $option, string $userId = ''): bool|string|int|array|null
{
    if (empty($userId)) {
        $userId = get_current_user_id();
    }

    if (null !== get_user_attribute($userId, $option)) {
        $result = get_user_attribute($userId, $option);
    } else {
        $result = null;
    }

    /**
     * Filters a specific user option value.
     *
     * The dynamic portion of the hook name, `$option`, refers to the user option name.
     *
     * @file core/Shared/Helpers/user.php
     * @param string|bool|int|array|null  $result Value for the user's option; if not exist, then null.
     * @param string $option Name of the option being retrieved.
     * @param string $userId ID of the user whose option is being retrieved.
     */
    return __observer()->filter->applyFilter("get.user.option.{$option}", $result, $option, $userId);
}

/**
 * Insert a user into the database.
 *
 * @file core/Shared/Helpers/user.php
 * @param array|ServerRequestInterface|User $userdata An array, object or User object of user data arguments.
 *
 *  {
 *      @type string $id User's ID. If supplied, the user will be updated.
 *      @type string $pass The plain-text user password.
 *      @type string $login The user's login username.
 *      @type string $fname The user's first name.
 *      @type string $mname The user's middle name.
 *      @type string $lname The user's last name.
 *      @type string $bio The user's biographical description.
 *      @type string $email The user's email address.
 *      @type string $url The user's url.
 *      @type string $status The user's status.
 *      @type int $adminLayout The user's admin layout option.
 *      @type int $adminSidebar The user's admin sidebar option
 *      @type string $adminSkin The user's admin skin option.
 *      @type string $registered Date the user registered. Format is 'Y-m-d H:i:s'.
 *      @type string $modified Date the user's account was updated. Format is 'Y-m-d H:i:s'.
 *  }
 *
 * @return string|Error The newly created user's user_id or Error if user could not be created.
 * @throws CommandPropertyNotFoundException
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 * @throws InvalidArgumentException
 */
function cms_insert_user(array|ServerRequestInterface|User $userdata): string|Error
{
    if ($userdata instanceof ServerRequestInterface) {
        $userdata = $userdata->getParsedBody();
    } elseif ($userdata instanceof User) {
        $userdata = $userdata->toArray(includePassword: true);
    }

    $defaults = [
        'url' => '',
        'bio' => '',
        'timezone' => config()->string('app.timezone'),
        'dateFormat' => 'd F Y',
        'timeFormat' => 'h:i A',
        'locale' => config()->string(key: 'app.locale'),
    ];

    $userdata = Utils::parseArgs($userdata, $defaults);
    $existing = false;

    // Are we updating or creating?
    if (!empty($userdata['id']) && !is_false__(get_user_by('id', $userdata['id']))) {
        $update = true;
        $userId = UserId::fromString($userdata['id']);
        $userToken = $userdata['token'];
        /** @var User $oldUserData */
        $oldUserData = get_userdata($userId->toNative());

        if (!$oldUserData) {
            return new UserError(message: trans_html(string: 'Invalid user id.'));
        }

        // hashed in cms_update_user(), plaintext if called directly
        $userPass = $userdata['pass'] ?? $oldUserData->pass;

        /**
         * If a user already exists in the system, then the user
         * will be added to the current site.
         */
        if (isset($userdata['user_exists']) && $userdata['user_exists'] === 'true') {
            $existing = true;
        }

        /**
         * Create a new user object.
         *
         * @var User $user
         */
        $user = Devflow::$PHP->make(User::class);
        $user->id = $userId->toNative();
        $user->token = $userToken;
        $user->pass = $userPass;
        $user->role = $userdata['role'];
    } else {
        $update = false;
        $userId = new UserId();

        if (mb_strlen($userdata['pass']) < config()->integer(key: 'cms.password_length')) {
            return new UserError(message: trans_html(
                string: sprintf(
                    'Password must be at least %s characters long.',
                    config()->integer(key: 'cms.password_length')
                ),
            ));
        }
        /**
         * Hash the plaintext password.
         *
         * @param string $userPass Hashed password.
         */
        $userPass = Password::hash($userdata['pass']);

        /**
         * Create new User object.
         *
         * @var User $user
         */
        $user = Devflow::$PHP->make(User::class);
        $user->id = $userId->toNative();
        $user->token = UserToken::generateAsString();
        $user->pass = $userPass;
        $user->role = $userdata['role'];
    }

    //Remove any non-printable chars from the login string to see if we have ended up with an empty username
    $rawUserLogin = $userdata['login'];
    $sanitizedUserLogin = $user->login = Sanitizer::username($rawUserLogin, true);
    /**
     * Filters a username after it has been sanitized.
     *
     * This filter is called before the user is created or updated.
     *
     * @file core/Shared/Helpers/user.php
     * @param string $sanitizedUserLogin Username after it has been sanitized.
     * @param string $rawUserLogin The user's login.
     */
    $preUserLogin = __observer()->filter->applyFilter(
        'pre.user.login',
        (string) $sanitizedUserLogin,
        (string) $rawUserLogin
    );

    //Remove any non-printable chars from the login string to see if we have ended up with an empty username
    $userLogin = trim__($preUserLogin);

    // userLogin must be between 3 and 60 characters.
    if (empty($userLogin)) {
        return new UserError(message: trans_html(
            string: 'Cannot create a user with an empty username.',
        ));
    } elseif (mb_strlen($userLogin) < 3) {
        return new UserError(message: trans_html(
            string: 'Username must be at least 3 characters long.',
        ));
    } elseif (mb_strlen($userLogin) > 60) {
        return new UserError(message: trans_html(
            string: 'Username may not be longer than 60 characters.',
        ));
    }

    if (!$update && username_exists($userLogin)) {
        return new UserError(message: trans_html(string: 'Sorry, that username cannot be used.'));
    }

    /**
     * Filters the list of blacklisted usernames.
     *
     * @file core/Shared/Helpers/user.php
     * @param array $usernames Array of blacklisted usernames.
     */
    $illegalLogins = (array) __observer()->filter->applyFilter('illegal.user.logins', blacklisted_usernames());

    if (in_array(strtolower($userLogin), array_map('\strtolower', $illegalLogins))) {
        return new UserError(
            message: sprintf(
                trans(
                    'Sorry, the username <strong>%s</strong> is not allowed.',
                ),
                $userLogin
            )
        );
    }

    $userUrl = $userdata['url'];

    $rawUserEmail = $userdata['email'];
    $sanitizedUserEmail = $user->email = Sanitizer::item($rawUserEmail, 'email');
    /**
     * Filters a user's email before the user is created or updated.
     *
     * @file core/Shared/Helpers/user.php
     * @param string $sanitizedUserEmail User email after it has been sanitized
     * @param string $rawUserEmail The user's email.
     */
    $userEmail = __observer()->filter->applyFilter(
        'pre.user.email',
        (string) $sanitizedUserEmail,
        (string) $rawUserEmail
    );
    /*
     * If there is no update, just check for `email_exists`. If there is an update,
     * check if current email and new email are the same, or not, and check `email_exists`
     * accordingly.
     */
    if (
        (
            is_false__($update) || (
                    !is_false__($oldUserData)
                    && 0 !== strcasecmp(
                        $userEmail,
                        $oldUserData->email
                    )
            )
        ) && email_exists($userEmail)
    ) {
        return new UserError(
            message: trans_html(string: 'Sorry, that email address cannot be used.')
        );
    }

    $rawUserFname = $userdata['fname'];
    $sanitizedUserFname = $user->fname = Sanitizer::item($userdata['fname']);
    /**
     * Filters a user's first name before the user is created or updated.
     *
     * @file core/Shared/Helpers/user.php
     * @param string $sanitizedUserFname User first name after it has been sanitized.
     * @param string $rawUserFname The user's first name.
     */
    $userFname = __observer()->filter->applyFilter(
        'pre.user.fname',
        (string) $sanitizedUserFname,
        (string) $rawUserFname
    );

    $rawUserMname = $userdata['mname'];
    $sanitizedUserMname = $user->mname = Sanitizer::item($userdata['mname']);
    /**
     * Filters a user's middle name before the user is created or updated.
     *
     * @file core/Shared/Helpers/user.php
     * @param string $sanitizedUserMname User middlename after it has been sanitized.
     * @param string $rawUserMname The user's middle name.
     */
    $userMname = __observer()->filter->applyFilter(
        'pre.user.mname',
        (string) $sanitizedUserMname,
        (string) $rawUserMname
    );

    $rawUserLname = $userdata['lname'];
    $sanitizedUserLname = $user->lname = Sanitizer::item($userdata['lname']);
    /**
     * Filters a user's last name before the user is created or updated.
     *
     * @file core/Shared/Helpers/user.php
     * @param string $sanitizedUserLname User last name after it has been sanitized.
     * @param string $rawUserLname The user's last name.
     */
    $userLname = __observer()->filter->applyFilter(
        'pre.user.lname',
        (string) $sanitizedUserLname,
        (string) $rawUserLname
    );

    $rawUserBio = $userdata['bio'];
    $sanitizedUserBio = Sanitizer::item($userdata['bio']);
    /**
     * Filters a user's bio before the user is created or updated.
     *
     * @file core/Shared/Helpers/user.php
     * @param string $sanitizedUserBio User bio after it has been sanitized.
     * @param string $rawUserBio The user's bio.
     */
    $userBio = __observer()->filter->applyFilter(
        'pre.user.bio',
        (string) $sanitizedUserBio,
        (string) $rawUserBio
    );

    $rawUserTimezone = $userdata['timezone'];
    $sanitizedUserTimezone = $user->timezone = Sanitizer::item($userdata['timezone']);
    /**
     * Filters a user's timezone before the user is created or updated.
     *
     * @file core/Shared/Helpers/user.php
     * @param string $sanitizedUserTimezone User timezone after it has been sanitized.
     * @param string $rawUserTimezone The user's timezone.
     */
    $userTimezone = __observer()->filter->applyFilter(
        'pre.user.timezone',
        (string) $sanitizedUserTimezone,
        (string) $rawUserTimezone
    );

    $rawUserDateFormat = $userdata['dateFormat'];
    $sanitizedUserDateFormat = $user->dateFormat = Sanitizer::item($userdata['dateFormat']);
    /**
     * Filters a user's date format before the user is created or updated.
     *
     * @file core/Shared/Helpers/user.php
     * @param string $sanitizedUserDateFormat User date format after it has been sanitized.
     * @param string $rawUserDateFormat The user's date format.
     */
    $userDateFormat = __observer()->filter->applyFilter(
        'pre.user.date.format',
        (string) $sanitizedUserDateFormat,
        (string) $rawUserDateFormat
    );

    $rawUserTimeFormat = $userdata['timeFormat'];
    $sanitizedUserTimeFormat = $user->timeFormat = Sanitizer::item($userdata['timeFormat']);
    /**
     * Filters a user's time format before the user is created or updated.
     *
     * @file core/Shared/Helpers/user.php
     * @param string $sanitizedUserTimeFormat User time format after it has been sanitized.
     * @param string $rawUserTimeFormat The user's time format.
     */
    $userTimeFormat = __observer()->filter->applyFilter(
        'pre.user.time.format',
        (string) $sanitizedUserTimeFormat,
        (string) $rawUserTimeFormat
    );

    $rawUserLocale = $userdata['locale'];
    $sanitizedUserLocale = Sanitizer::item($userdata['locale']);
    /**
     * Filters a user's locale before the user is created or updated.
     *
     * @file core/Shared/Helpers/user.php
     * @param string $sanitizedUserLocale User locale after it has been sanitized.
     * @param string $rawUserLocale       The user's locale.
     */
    $userLocale = __observer()->filter->applyFilter(
        'pre.user.locale',
        (string) $sanitizedUserLocale,
        (string) $rawUserLocale
    );

    $rawUserStatus = $userdata['status'];
    $sanitizedUserStatus = Sanitizer::item($userdata['status']);
    /**
     * Filters a user's status before the user is created or updated.
     *
     * @file core/Shared/Helpers/user.php
     * @param string $sanitizedUserStatus User status after it has been sanitized.
     * @param string $rawUserStatus The user's status.
     */
    $attribute['status'] = __observer()->filter->applyFilter(
        'pre.user.status',
        (string) $sanitizedUserStatus,
        (string) $rawUserStatus
    );

    $attribute['role'] = $user->role;

    $userAdminLayout = '0';

    $attribute['admin.layout'] = isset($userdata['adminLayout']) ? (int) $userdata['adminLayout'] : $userAdminLayout;

    $userAdminSidebar = '0';

    $attribute['admin.sidebar'] = isset($userdata['adminSidebar']) ? (int) $userdata['adminSidebar'] : $userAdminSidebar;

    $userAdminSkin = 'skin-red';

    $attribute['admin.skin'] = !empty($userdata['adminSkin']) ? $userdata['adminSkin'] : $userAdminSkin;

    $userRegistered = QubusDateTimeImmutable::now();

    $userModified = QubusDateTimeImmutable::now();

    $userActivationKey = $user->activationKey = empty($userdata['activationKey']) ? '' : $userdata['activationKey'];

    // Content custom fields.
    $attributeFields = $userdata['user_field'] ?? [];
    $attribute = array_merge($attributeFields, $attribute);

    $compacted = [
        'login' => $userLogin,
        'fname' => $userFname,
        'mname' => $userMname,
        'lname' => $userLname,
        'pass' => $userPass ?? '',
        'email' => $userEmail,
        'url' => $userUrl,
        'bio' => $userBio ?? '',
        'timezone' => $userTimezone,
        'dateFormat' => $userDateFormat,
        'timeFormat' => $userTimeFormat,
        'locale' => $userLocale,
        'registered' => $userRegistered->format('Y-m-d H:i:s'),
        'activationKey' => $userActivationKey,
    ];
    $userdata = unslash($compacted);

    /**
     * Filters user data before the record is created or updated.
     *
     * It only includes data in the user's table, not any user metadata.
     *
     * @file core/Shared/Helpers/user.php
     * @param array $userdata {
     *     Values and keys for the user.
     *
     *      @type string $login        The user's login.
     *      @type string $fname        The user's first name.
     *      @type string $mname        The user's middle name.
     *      @type string $lname        The user's last name.
     *      @type string $pass         The user's password.
     *      @type string $email        The user's email.
     *      @type string $url          The user's url.
     *      @type string $bio          The user's bio.
     *      @type string $timezone     The user's timezone.
     *      @type string $dateFormat   The user's date format.
     *      @type string $timeFormat   The user's time format.
     *      @type string $locale       The user's locale.
     *      @type string $registered   Timestamp describing the moment when the user registered. Defaults to
     *                                 Y-m-d h:i:s
     *      @type string $activationKey
     * }
     * @param bool     $update Whether the user is being updated rather than created.
     * @param string|null $userID ID of the user to be updated, or NULL if the user is being created.
     */
    __observer()->filter->applyFilter(
        'pre.cms.insert.user.data',
        $userdata,
        $update,
        $update ? $userId->toNative() : null
    );

    /**
     * Filters a user's attribute values and keys immediately after the user is created or updated
     * and before any user attribute is inserted or updated.
     *
     * @file core/Shared/Helpers/user.php
     * @param array $attribute {
     *     Default attribute values and keys for the user.
     *
     *     @type string $role           The user's role.
     *     @type string $status         The user's status.
     *     @type int    $admin.layout   The user's layout option.
     *     @type int    $admin.sidebar  The user's sidebar option.
     *     @type int    $admin.skin     The user's skin option.
     * }
     * @param object $user  User object.
     * @param bool $update  Whether the user is being updated rather than created.
     */
    $attributes = __observer()->filter->applyFilter('insert.user.attribute', $attribute, $user, $update);

    if (is_false__($update)) {
        try {
            $command = new CreateUserCommand([
                'id' => UserId::fromString($user->id),
                'fname' => new StringLiteral($userFname),
                'mname' => new StringLiteral($userMname ?? ''),
                'lname' => new StringLiteral($userLname),
                'email' => new EmailAddress($userEmail),
                'login' => new Username($userLogin),
                'token' => UserToken::fromString($user->token),
                'pass' => new StringLiteral($user->pass),
                'url' => new StringLiteral($userUrl ?? ''),
                'bio' => new StringLiteral($userBio),
                'timezone' => new StringLiteral($userTimezone),
                'dateFormat' => new StringLiteral($userDateFormat ?? 'd F Y'),
                'timeFormat' => new StringLiteral($userTimeFormat ?? 'h:i A'),
                'locale' => new StringLiteral($userLocale ?? 'en'),
                'activationKey' => new StringLiteral($userActivationKey),
                'registered' => $userRegistered,
            ]);

            command($command);
        } catch (CommandCouldNotBeHandledException | UnresolvableCommandHandlerException | ReflectionException $e) {
            logger(level: 'error', message: $e->getMessage());
        }

        AttributesFactory::user()->createIfMissing(get_current_site_id(), $user->id);
    } else {
        /**
         * User object.
         */
        if ($userEmail !== $oldUserData->email || $user->pass !== $oldUserData->pass) {
            $user->activationKey = '';
        }

        try {
            $command = new UpdateUserCommand([
                'id' => UserId::fromString($user->id),
                'fname' => new StringLiteral($user->fname),
                'mname' => new StringLiteral($user->mname ?? ''),
                'lname' => new StringLiteral($user->lname),
                'email' => new EmailAddress($user->email),
                'login' => new Username($user->login),
                'token' => UserToken::fromString($user->token),
                'url' => new StringLiteral($userUrl ?? ''),
                'bio' => new StringLiteral($userBio),
                'timezone' => new StringLiteral($user->timezone),
                'dateFormat' => new StringLiteral($user->dateFormat ?? 'd F Y'),
                'timeFormat' => new StringLiteral($user->timeFormat ?? 'h:i A'),
                'locale' => new StringLiteral($userLocale ?? 'en'),
                'modified' => $userModified,
                'activationKey' => new StringLiteral($userActivationKey),
            ]);

            command($command);

            if ($existing) {
                AttributesFactory::user()->createIfMissing(get_current_site_id(), $user->id);
            }
        } catch (UnresolvableCommandHandlerException | ReflectionException $e) {
            logger(level: 'error', message: $e->getMessage());
        }
    }

    if (!empty($attributes['role']) && !empty($attributes['status'])) {
        foreach ($attributes as $key => $value) {
            update_user_attribute($user->id, $key, $value);
        }
        /** Set the user's role */
        $user->setRole($user->role);
    }

    /** Flush the cache. */
    UserCachePsr16::clean($user);
    SimpleCacheObjectCacheFactory::make(namespace: 'users')->clear();

    if ($update) {
        /**
         * Fires immediately after an existing user is updated.
         *
         * @file core/Shared/Helpers/user.php
         * @param string $userId    User ID.
         * @param User $oldUserData Object containing user's data prior to update.
         */
        __observer()->action->doAction('profile_update', $userId->toNative(), $oldUserData);
    } else {
        /**
         * Fires immediately after a new user is registered.
         *
         * @file core/Shared/Helpers/user.php
         * @param string $userId User ID.
         */
        __observer()->action->doAction('user_register', $userId->toNative());
    }

    return $userId->toNative();
}

/**
 * Update a user in the database.
 *
 * It is possible to update a user's password by specifying the 'pass'
 * value in the $userdata parameter array.
 *
 * See {@see cms_insert_user()} For what fields can be set in $userdata.
 *
 * @file core/Shared/Helpers/user.php
 * @param array|ServerRequestInterface|User $userdata An array of user data or a user object of type stdClass or User.
 * @return string|Error The updated user's id or return an Error if the user could not be updated.
 * @throws CommandPropertyNotFoundException
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 */
function cms_update_user(array|ServerRequestInterface|User $userdata): string|UserError
{
    if ($userdata instanceof ServerRequestInterface) {
        $userdata = $userdata->getParsedBody();
    } elseif ($userdata instanceof User) {
        $userdata = $userdata->toArray(includePassword: true);
    }

    $id = $userdata['id'] ?? '';
    if (!$id) {
        return new UserError(message: trans_html(string: 'Invalid user id.'));
    }

    // First, get all the original fields
    /** @var User $userObj */
    $userObj = get_userdata($id);
    if (!$userObj) {
        return new UserError(message: trans_html(string: 'Invalid user id.'));
    }

    $user = get_object_vars($userObj);

    $userAttributes = [
        'role',
        'status',
        'adminLayout',
        'adminSidebar',
        'adminSkin'
    ];

    foreach ($userAttributes as $key) {
        $user[$key] = get_user_option($key, $user['id']);
    }

    if (!empty($userdata['pass']) && $userdata['pass'] !== $userObj->pass) {
        // If password is changing, hash it now
        $plaintextPass = $userdata['pass'];
        $userdata['pass'] = Password::hash($plaintextPass);
    }

    if (isset($userdata['email']) && $user['email'] !== $userdata['email']) {
        /**
         * Filters whether to send the email change email.
         *
         * @file core/Shared/Helpers/user.php
         * @see cms_insert_user() For `$user` and `$userdata` fields.
         *
         * @param bool  $send     Whether to send the email.
         * @param array $user     The original user array before changes.
         * @param array $userdata The updated user array.
         *
         */
        $sendEmailChangeEmail = __observer()->filter->applyFilter(
            'send.email.change.email',
            true,
            $user,
            $userdata
        );
    }

    // Merge old and new fields with new fields overwriting old ones.
    $userdata = array_merge($user, $userdata);
    $userId = cms_insert_user($userdata);

    if (!$userId instanceof Error) {
        if (!empty($sendEmailChangeEmail)) {
            /**
             * Fires when user is updated successfully.
             *
             * @file core/Shared/Helpers/user.php
             * @param array $user     The original user array before changes.
             * @param array $userdata The updated user array.
             */
            __observer()->action->doAction('email_change_email', $user, $userdata);
        }
    }

    /**
     * Update the cookies if the username changed.
     * @var User $currentUser
     */
    $currentUser = cms_get_current_user();
    if (is_user_logged_in() && $currentUser->id === $id) {
        if (isset($userdata['login']) && $userdata['token'] !== $currentUser->token) {
            /**
             * Retrieve data from the old secure cookie to set expiration.
             */
            $oldCookieData = get_secure_cookie_data('USERCOOKIEID');
            $rememberme = $oldCookieData->remember === 'yes' ? $oldCookieData->remember : 'no';
            /**
             * Clear the old cookie data.
             */
            cms_clear_auth_cookie();
            /**
             * Set the new secure cookie.
             */
            cms_set_auth_cookie((array) $userdata, $rememberme);
        }
    }

    return $userId;
}

/**
 * Email sent to user with changed/updated email.
 *
 * @file core/Shared/Helpers/user.php
 * @param object|array $user Original user array.
 * @param array $userdata Updated user array.
 * @return bool True on success, false on failure or Exception.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function send_email_change_email(object|array $user, array $userdata): bool
{
    if ($user instanceof User) {
        $user = $user->toArray();
    }

    if ($user['email'] === $userdata['email']) {
        return true;
    }

    queue(
        new EmailChangeNotification([
            'login' => (string) $user['login'],
            'admin' => (string) get_option(key: 'admin_email'),
            'sitename' => (string) get_option(key: 'sitename'),
            'email' => (string) $userdata['email'],
            'url' => sprintf(site_url('admin/%s/'), Devflow::$PHP->configContainer->string(key: 'auth.login_route')),
        ])
    )
    ->createItem();

    return true;
}

/**
 * An extensive list of blacklisted usernames.
 *
 * Uses `blacklisted.usernames` filter.
 *
 * @file core/Shared/Helpers/user.php
 * @return array Array of blacklisted usernames.
 * @throws Exception
 */
function blacklisted_usernames(): array
{
    $blacklist = [
        '.htaccess', '.htpasswd', '.well-known', '400', '401', '403', '404',
        '405', '406', '407', '408', '409', '410', '411', '412', '413', '414',
        '415', '416', '417', '421', '422', '423', '424', '426', '428', '429',
        '431', '500', '501', '502', '503', '504', '505', '506', '507', '508',
        '509', '510', '511', 'about', 'about-us', 'abuse', 'access', 'account',
        'accounts', 'ad', 'add', 'admin', 'administration', 'administrator',
        'ads', 'advertise', 'advertising', 'aes128-ctr', 'aes128-gcm',
        'aes192-ctr', 'aes256-ctr', 'aes256-gcm', 'affiliate', 'affiliates',
        'ajax', 'alert', 'alerts', 'alpha', 'amp', 'analytics', 'api', 'app',
        'apps', 'asc', 'assets', 'atom', 'auth', 'authentication', 'authorize',
        'autoconfig', 'autodiscover', 'avatar', 'backup', 'banner', 'banners',
        'beta', 'billing', 'billings', 'blog', 'blogs', 'board', 'bookmark',
        'bookmarks', 'broadcasthost', 'business', 'buy', 'cache', 'calendar',
        'campaign', 'captcha', 'careers', 'cart', 'cas', 'categories',
        'category', 'cdn', 'cgi', 'cgi-bin', 'chacha20-poly1305', 'change',
        'channel', 'channels', 'chart', 'chat', 'checkout', 'clear', 'client',
        'close', 'cms', 'com', 'comment', 'comments', 'community', 'compare',
        'compose', 'config', 'connect', 'contact', 'contest', 'cookies', 'copy',
        'copyright', 'count', 'create', 'crossdomain.xml', 'css',
        'curve25519-sha256', 'customer', 'customers', 'customize', 'dashboard',
        'db', 'deals', 'debug', 'delete', 'desc', 'dev', 'developer',
        'developers', 'devflow', 'diffie-hellman-group-exchange-sha256',
        'diffie-hellman-group14-sha1', 'disconnect', 'discuss', 'dns', 'dns0',
        'dns1', 'dns2', 'dns3', 'dns4', 'docs', 'documentation', 'domain',
        'download', 'downloads', 'downvote', 'draft', 'drop', 'drupal',
        'ecdh-sha2-nistp256', 'ecdh-sha2-nistp384', 'ecdh-sha2-nistp521',
        'edit', 'editor', 'email', 'enterprise', 'error', 'errors', 'event',
        'events', 'example', 'exception', 'exit', 'explore', 'export',
        'extensions', 'false', 'family', 'faq', 'faqs', 'favicon.ico',
        'features', 'feed', 'feedback', 'feeds', 'file', 'files', 'filter',
        'follow', 'follower', 'followers', 'following', 'fonts', 'forgot',
        'forgot-password', 'forgotpassword', 'form', 'forms', 'forum', 'forums',
        'friend', 'friends', 'ftp', 'get', 'git', 'go', 'group', 'groups',
        'guest', 'guidelines', 'guides', 'head', 'header', 'help', 'hide',
        'hmac-sha', 'hmac-sha1', 'hmac-sha1-etm', 'hmac-sha2-256',
        'hmac-sha2-256-etm', 'hmac-sha2-512', 'hmac-sha2-512-etm', 'home',
        'host', 'hosting', 'hostmaster', 'htpasswd', 'http', 'httpd', 'https',
        'humans.txt', 'icons', 'images', 'imap', 'img', 'import', 'info',
        'insert', 'investors', 'invitations', 'invite', 'invites', 'invoice',
        'is', 'isatap', 'issues', 'it', 'jobs', 'join', 'joomla', 'js', 'json',
        'keybase.txt', 'learn', 'legal', 'license', 'licensing', 'limit',
        'live', 'load', 'local', 'localdomain', 'localhost', 'lock', 'login',
        'logout', 'lost-password', 'mail', 'mail0', 'mail1', 'mail2', 'mail3',
        'mail4', 'mail5', 'mail6', 'mail7', 'mail8', 'mail9', 'mailer-daemon',
        'mailerdaemon', 'map', 'marketing', 'marketplace', 'master', 'me',
        'media', 'member', 'members', 'message', 'messages', 'metrics', 'mis',
        'mobile', 'moderator', 'modify', 'more', 'mx', 'my', 'net', 'network',
        'new', 'news', 'newsletter', 'newsletters', 'next', 'nil', 'no-reply',
        'nobody', 'noc', 'none', 'noreply', 'notification', 'notifications',
        'ns', 'ns0', 'ns1', 'ns2', 'ns3', 'ns4', 'ns5', 'ns6', 'ns7', 'ns8',
        'ns9', 'null', 'oauth', 'oauth2', 'offer', 'offers', 'online',
        'openid', 'order', 'orders', 'overview', 'owner', 'page', 'pages',
        'partners', 'passwd', 'password', 'pay', 'payment', 'payments',
        'photo', 'photos', 'pixel', 'plans', 'plugins', 'policies', 'policy',
        'pop', 'pop3', 'popular', 'portfolio', 'content', 'postfix', 'postmaster',
        'poweruser', 'preferences', 'premium', 'press', 'previous', 'pricing',
        'print', 'privacy', 'privacy-policy', 'private', 'prod', 'product',
        'production', 'profile', 'profiles', 'project', 'projects', 'public',
        'purchase', 'put', 'quota', 'qubus', 'qubuscms', 'redirect', 'reduce',
        'refund', 'refunds', 'register', 'registration', 'remove', 'replies',
        'reply', 'request', 'request-password', 'reset', 'reset-password',
        'response', 'report', 'return', 'returns', 'review', 'reviews',
        'robots.txt', 'root', 'rootuser', 'rsa-sha2-2', 'rsa-sha2-512', 'rss',
        'rules', 'sales', 'save', 'script', 'sdk', 'search', 'secure',
        'security', 'select', 'services', 'session', 'sessions', 'settings',
        'setup', 'share', 'shift', 'shop', 'signin', 'signup', 'site', 'sitemap',
        'sites', 'smtp', 'sort', 'source', 'sql', 'ssh', 'ssh-rsa', 'ssl',
        'ssladmin', 'ssladministrator', 'sslwebmaster', 'stage', 'staging',
        'stat', 'static', 'statistics', 'stats', 'status', 'store', 'style',
        'styles', 'stylesheet', 'stylesheets', 'subdomain', 'subscribe', 'sudo',
        'super', 'superuser', 'support', 'survey', 'sync', 'sysadmin', 'system',
        'tablet', 'tag', 'tags', 'team', 'telnet', 'terms', 'terms-of-use',
        'test', 'testimonials', 'theme', 'themes', 'today', 'tools', 'topic',
        'topics', 'tour', 'training', 'translate', 'translations', 'trending',
        'trial', 'tritan', 'tritancms', 'true', 'ttcms', 'umac-128', 'undefined',
        'unfollow', 'unsubscribe', 'update', 'upgrade', 'usenet', 'user',
        'username', 'users', 'uucp', 'var', 'verify', 'video', 'view',
        'void', 'vote', 'webmail', 'webmaster', 'website', 'widget', 'widgets',
        'wiki', 'wordpress', 'wpad', 'write', 'www', 'www-data', 'www1', 'www2',
        'www3', 'www4', 'you', 'yourname', 'yourusername', 'zlib', 'getdevflow'
    ];

    return __observer()->filter->applyFilter('blacklisted.usernames', $blacklist);
}

/**
 * Resets a user's password.
 *
 * @file core/Shared/Helpers/user.php
 * @param string $userId ID of user whose password is to be reset.
 * @return bool|string User password on success or Exception on failure.
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws ReflectionException
 * @throws TypeException
 * @throws UnresolvableCommandHandlerException
 */
function reset_password(string $userId): bool|string
{
    $password = generate_random_password(config()->integer(key: 'cms.password_length'));

    try {
        $command = new UpdateUserPasswordCommand([
            'id' => UserId::fromString($userId),
            'token' => new UserToken(),
            'pass' => new StringLiteral($password),
        ]);

        command($command);

        return $password;
    } catch (CommandPropertyNotFoundException $e) {
        logger(level: 'error', message: $e->getMessage());
        Devflow::$PHP->flash->error($e->getMessage());
    }

    return false;
}

/**
 * Print a dropdown list of users.
 *
 * @file core/Shared/Helpers/user.php
 * @param string $userId The user's ID to ignore.
 * @return void Dropdown list of users.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_users_reassign(string $userId = ''): void
{
    $dfdb = dfdb();

    $sql = "SELECT u.user_id FROM {$dfdb->basePrefix}user u " .
    "JOIN {$dfdb->basePrefix}site_user su " .
    "ON u.user_id = su.user_id " .
    "WHERE u.user_id NOT IN (?) 
            AND su.site_id = ?";

    $listUsers = $dfdb->getResults($dfdb->prepare($sql, [$userId, get_current_site_id()]), Database::ARRAY_A);

    foreach ($listUsers as $user) {
        echo '<option value="' . esc_html($user['user_id']) . '">' . get_name(esc_html($user['user_id'])) . '</option>';
    }
}

/**
 * Retrieves a list of users by site_key.
 *
 * @file core/Shared/Helpers/user.php
 * @param string $siteKey Site key.
 * @return array|false|string User array on success.
 */
function get_users_by_site_key(string $siteKey = ''): array|string|bool
{
    $dfdb = dfdb();

    $sql = "SELECT u.* " .
    "FROM {$dfdb->basePrefix}user u " .
    "JOIN {$dfdb->basePrefix}site_user su ON u.user_id = su.user_id " .
    "JOIN {$dfdb->basePrefix}site s ON su.site_id = s.site_id " .
    "WHERE s.site_key = ?";

    return $dfdb->getResults($dfdb->prepare($sql, [$siteKey]), Database::ARRAY_A);
}

/**
 * Returns the logged-in user's timezone.
 *
 * @file core/Shared/Helpers/user.php
 * @return mixed Logged in user's timezone or system's timezone if false.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_user_timezone(): mixed
{
    $user = get_user_by('id', get_current_user_id());
    if (is_user_logged_in() && $user !== false) {
        return $user->timezone;
    }
    return get_option(key: 'site_timezone') ?: config()->string(key: 'app.timezone');
}

/**
 * Returns the logged-in user's date format.
 *
 * @file core/Shared/Helpers/user.php
 * @return mixed Logged in user's date format or system's date format if false.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_user_date_format(): mixed
{
    $userDateFormat = get_user_by('id', get_current_user_id());
    if (is_user_logged_in() && $userDateFormat !== false) {
        return $userDateFormat->dateFormat;
    }
    return get_option(key: 'date_format');
}

/**
 * Returns the logged-in user's time format.
 *
 * @file core/Shared/Helpers/user.php
 * @return mixed Logged in user's time format or system's time format if false.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_user_time_format(): mixed
{
    $userTimeFormat = get_user_by('id', get_current_user_id());
    if (is_user_logged_in() && $userTimeFormat !== false) {
        return $userTimeFormat->timeFormat;
    }
    return get_option(key: 'time_format');
}

/**
 * Returns the logged in user's datetime format.
 *
 * @file core/Shared/Helpers/user.php
 * @return string Logged in user's datetime format or system's datetime format.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_user_datetime_format(): string
{
    $dateFormat = get_user_date_format();
    $timeFormat = get_user_time_format();
    return __observer()->filter->applyFilter(
        'user.datetime.format',
        concat_ws($dateFormat, $timeFormat, ' '),
        $timeFormat,
        $dateFormat
    );
}

/**
 * Returns datetime based on user's date format, time format, and timezone.
 *
 * @file core/Shared/Helpers/user.php
 * @param string $string Datetime string.
 * @param string $format Format of the datetime string.
 * @return string Datetime string based on logged in user's date format,
 *                time format and timezone. Otherwise, it will use system settings.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_user_datetime(string $string, string $format = 'Y-m-d H:i:s'): string
{
    $datetime = new DateTime($string, get_user_timezone())->getDateTime();
    return $datetime->format($format);
}
