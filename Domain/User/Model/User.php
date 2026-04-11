<?php

declare(strict_types=1);

namespace App\Domain\User\Model;

use App\Infrastructure\Persistence\Cache\UserCachePsr16;
use Qubus\Expressive\Database;
use App\Infrastructure\Services\AttributesFactory;
use App\Shared\Services\Sanitizer;
use App\Shared\Services\SimpleCacheObjectCacheFactory;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\EventDispatcher\ActionFilter\Action;
use Qubus\Exception\Exception;
use ReflectionException;
use stdClass;

use function App\Shared\Helpers\get_current_site_id;
use function md5;
use function Qubus\Security\Helpers\esc_html;
use function Qubus\Security\Helpers\purify_html;
use function Qubus\Support\Helpers\convert_array_to_object;
use function Qubus\Support\Helpers\is_null__;
use function sprintf;
use function strtolower;

/**
 * @property string $id
 * @property string $login
 * @property string $token
 * @property string $fname
 * @property string $mname
 * @property string $lname
 * @property string $email
 * @property string $pass
 * @property string $url
 * @property string $bio
 * @property string $status
 * @property string $role
 * @property string $admin_layout
 * @property string admin_sidebar
 * @property string $admin_skin
 * @property string $timezone
 * @property string $dateFormat
 * @property string $timeFormat
 * @property string locale
 * @property string $registered
 * @property string $modified
 * @property string $activationKey
 */
final class User extends stdClass
{
    public function __construct(protected Database $dfdb)
    {
    }

    /**
     * Return only the main user fields.
     *
     * @param string $field The field to query against: 'id', 'token' or 'login'.
     * @param string $value The field value
     * @return User|false Raw user object
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function findBy(string $field, string $value): User|false
    {
        if ('' === $value) {
            return false;
        }

        $userId = match ($field) {
            'id' => $value,
            'token' => SimpleCacheObjectCacheFactory::make(namespace: 'usertoken')->get(md5($value), ''),
            'login' => SimpleCacheObjectCacheFactory::make(
                namespace: 'userlogin'
            )->get(md5(Sanitizer::username($value)), ''),
            'email' => SimpleCacheObjectCacheFactory::make('useremail')->get(md5($value), ''),
            default => false,
        };

        $dbField = match ($field) {
            'id' => 'user_id',
            'token' => 'user_token',
            'login' => 'user_login',
            'email' => 'user_email',
            default => false,
        };

        $user = null;

        if ('' !== $userId) {
            if ($data = SimpleCacheObjectCacheFactory::make(namespace: 'users')->get(md5($userId))) {
                is_array($data) ? convert_array_to_object($data) : $data;
            }
        }

        if (
            !$data = $this->dfdb->getRow(
                $this->dfdb->prepare(
                    sprintf(
                        "SELECT u.*, s.user_attribute
                            FROM {$this->dfdb->basePrefix}user u 
                            JOIN {$this->dfdb->basePrefix}site_user s 
                            ON u.user_id = s.user_id
                            WHERE u.%s = ? 
                            AND s.site_id = ?",
                        $dbField
                    ),
                    [
                        $value,
                        get_current_site_id(),
                    ]
                ),
                Database::ARRAY_A
            )
        ) {
            return false;
        }

        if (!is_null__($data)) {
            $user = $this->create($data);
            UserCachePsr16::update($user);
        }

        if (is_array($user)) {
            $user = convert_array_to_object($user);
        }

        return $user;
    }

    /**
     * Create a new instance of User. Optionally populating it
     * from a data array.
     *
     * @param array $data
     * @return User
     * @throws Exception
     */
    public function create(array $data = []): User
    {
        $user = $this->__create();
        if ($data) {
            $user = $this->populate($user, $data);
        }
        return $user;
    }

    /**
     * Create a new User object.
     *
     * @return User
     */
    protected function __create(): User
    {
        return new User($this->dfdb);
    }

    /**
     * @throws Exception
     */
    public function populate(User $user, array $data = []): self
    {
        $user->id = esc_html(string: $data['user_id']) ?? null;
        $user->login = esc_html(string: $data['user_login']) ?? null;
        $user->token = esc_html(string: $data['user_token']) ?? null;
        $user->fname = esc_html(string: $data['user_fname']) ?? null;
        $user->mname = esc_html(string: $data['user_mname']) ?? null;
        $user->lname = esc_html($data['user_lname']) ?? null;
        $user->email = esc_html(string: $data['user_email']) ?? null;
        $user->pass = esc_html(string: $data['user_pass']) ?? null;
        $user->url = esc_html(string: $data['user_url']) ?? null;
        $user->bio = isset($data['user_bio']) ? esc_html(string: $data['user_bio']) : null;
        $user->timezone = esc_html(string: $data['user_timezone']) ?? null;
        $user->dateFormat = esc_html(string: $data['user_date_format']) ?? null;
        $user->timeFormat = esc_html(string: $data['user_time_format']) ?? null;
        $user->locale = esc_html(string: $data['user_locale']) ?? null;
        $user->registered = isset($data['user_registered']) ? esc_html(string: $data['user_registered']) : null;
        $user->modified = isset($data['user_modified']) ? esc_html(string: $data['user_modified']) : null;
        $user->activationKey = is_null__($data['user_activation_key']) ?
        '' :
        esc_html(string: $data['user_activation_key']);

        return $user;
    }

    /**
     * Magic method for checking the existence of a certain custom field.
     *
     * @param string $key User attribute key to check if set.
     * @return bool Whether the given user attribute key is set.
     * @throws Exception
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __isset(string $key)
    {
        return false === AttributesFactory::user()->exists(get_current_site_id(), $this->id, $key);
    }

    /**
     * Magic method for accessing custom fields.
     *
     * @param string $key User attribute key to retrieve.
     * @return string Value of the given user attribute key (if set). If `$key` is 'id', the user ID.
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function __get(string $key): string
    {
        if (isset($this->{$key})) {
            $value = $this->{$key};
        } else {
            $value = AttributesFactory::user()->get(get_current_site_id(), $this->id, $key);
        }

        return purify_html($value);
    }

    /**
     * Magic method for setting custom user fields.
     *
     * This method does not update custom fields in the user document. It only stores
     * the value on the User instance.
     *
     * @param string $key   User attribute key.
     * @param mixed  $value User attribute value.
     */
    public function __set(string $key, mixed $value): void
    {
        if ('id' === strtolower($key)) {
            $this->id = $value;
            return;
        }

        $this->{$key} = $value;
    }

    /**
     * Magic method for unsetting a certain custom field.
     *
     * @param string $key User attribute key to unset.
     */
    public function __unset(string $key)
    {
        if (isset($this->{$key})) {
            unset($this->{$key});
        }
    }

    /**
     * Retrieve the value of a property or attribute key.
     *
     * Retrieves from the user and site_user table.
     *
     * @param string $key Property
     * @return string
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function get(string $key): string
    {
        return $this->__get($key);
    }

    /**
     * Determine whether a property or attribute key is set
     *
     * Consults the user and site_user tables.
     *
     * @param string $key Property
     * @return bool
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function isSet(string $key): bool
    {
        return $this->__isset($key);
    }

    /**
     * Return an array representation.
     *
     * @return array Array representation.
     */
    public function toArray(): array
    {
        unset($this->dfdb);

        return get_object_vars($this);
    }

    /**
     * @param string $role
     * @param string|null $siteId
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function setRole(string $role, ?string $siteId = null): void
    {
        if(is_null__($siteId)) {
            $siteId = get_current_site_id();
        }

        $oldRole = AttributesFactory::user()->get(siteId: $siteId, userId: $this->id, key: 'role');

        AttributesFactory::user()->set(siteId: $siteId, userId: $this->id, key: 'role', value: $role);

        /**
         * Fires after the user's role has been added/changed.
         *
         * @param string  $userId  The user id.
         * @param string  $siteId  The site id.
         * @param string  $role    The new role.
         * @param string  $oldRole The user's previous role.
         */
        Action::getInstance()->doAction('set_user_role', $this->id, $siteId, $role, $oldRole);
    }
}
