<?php

declare(strict_types=1);

namespace App\Domain\User\Model;

use App\Infrastructure\Persistence\Cache\UserCachePsr16;
use App\Infrastructure\Services\Trait\CleanAware;
use Qubus\Exception\Data\TypeException;
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

use function App\Shared\Helpers\get_current_site_id;
use function md5;
use function Qubus\Security\Helpers\purify_html;
use function sprintf;
use function strtolower;

final class User
{
    use CleanAware;

    public ?string $id = null;
    public ?string $login = null;
    public ?string $token = null;
    public ?string $fname = null;
    public ?string $mname = null;
    public ?string $lname = null;
    public ?string $email = null;
    public ?string $pass = null;
    public ?string $url = null;
    public ?string $bio = null;
    public ?string $timezone = null;
    public ?string $dateFormat = null;
    public ?string $timeFormat = null;
    public ?string $locale = null;
    public ?string $registered = null;
    public ?string $modified = null;
    public ?string $activationKey = null;

    /**
     * Runtime-only attributes. These do not automatically persist.
     *
     * @var array<string, mixed>
     */
    private array $attributes = [];

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
    public function findBy(string $field, string $value): self|false
    {
        if ($value === '') {
            return false;
        }

        $field = strtolower($field);

        $dbField = match ($field) {
            'id' => 'user_id',
            'token' => 'user_token',
            'login' => 'user_login',
            'email' => 'user_email',
            default => null,
        };

        if ($dbField === null) {
            return false;
        }

        $lookupValue = $this->normalizeLookupValue($field, $value);

        /**
         * If a secondary cache resolves login/email/token to a user ID,
         * query by user_id instead of querying by the original login/email/token.
         */
        if ($lookupValue !== null && $lookupValue !== '') {
            $cached = SimpleCacheObjectCacheFactory::make(namespace: 'users')
                    ->get(md5($lookupValue));

            if (is_array($cached)) {
                return $this->create($cached);
            }

            if ($cached instanceof self) {
                return $cached;
            }

            $dbField = 'user_id';
            $value = $lookupValue;
        }

        $data = $this->dfdb->getRow(
            $this->dfdb->prepare(
                sprintf(
                "SELECT u.*
                FROM {$this->dfdb->basePrefix}user u
                WHERE u.%s = ?",
                    $dbField
                ),
                [$value]
            ),
            Database::ARRAY_A
        );

        if (! is_array($data) || $data === []) {
            return false;
        }

        $user = $this->create($data);

        UserCachePsr16::update($user);

        return $user;
    }

    /**
     * @param string $field
     * @param string $value
     * @return string|null
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    private function normalizeLookupValue(string $field, string $value): ?string
    {
        return match ($field) {
            'id' => $value,

            'token' => SimpleCacheObjectCacheFactory::make(namespace: 'usertoken')
                    ->get(md5($value), null),

            'login' => SimpleCacheObjectCacheFactory::make(namespace: 'userlogin')
                    ->get(md5(Sanitizer::username($value)), null),

            'email' => SimpleCacheObjectCacheFactory::make(namespace: 'useremail')
                    ->get(md5(strtolower($value)), null),

            default => null,
        };
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
        $user = new self($this->dfdb);

        if ($data !== []) {
            $user->populate($data);
        }

        return $user;
    }

    /**
     * @throws Exception
     */
    public function populate(array $data = []): self
    {
        $data = $this->normalizeData($data);

        $this->id = $this->clean($data['id']);
        $this->login = $this->clean($data['login']);
        $this->token = $this->clean($data['token']);
        $this->fname = $this->clean($data['fname']);
        $this->mname = $this->clean($data['mname']);
        $this->lname = $this->clean($data['lname']);
        $this->email = $this->clean($data['email']);
        $this->pass = $data['pass'] !== null ? (string) $data['pass'] : null;
        $this->url = $this->clean($data['url']);
        $this->bio = $data['bio'] !== null ? purify_html((string) $data['bio']) : null;
        $this->timezone = $this->clean($data['timezone']);
        $this->dateFormat = $this->clean($data['dateFormat']);
        $this->timeFormat = $this->clean($data['timeFormat']);
        $this->locale = $this->clean($data['locale']);
        $this->registered = $this->clean($data['registered']);
        $this->modified = $this->clean($data['modified']);
        $this->activationKey = $this->clean($data['activationKey']);

        return $this;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeData(array $data): array
    {
        return [
            'id' => $data['id'] ?? $data['user_id'] ?? null,
            'login' => $data['login'] ?? $data['user_login'] ?? null,
            'token' => $data['token'] ?? $data['user_token'] ?? null,
            'fname' => $data['fname'] ?? $data['user_fname'] ?? null,
            'mname' => $data['mname'] ?? $data['user_mname'] ?? null,
            'lname' => $data['lname'] ?? $data['user_lname'] ?? null,
            'email' => $data['email'] ?? $data['user_email'] ?? null,
            'pass' => $data['pass'] ?? $data['user_pass'] ?? null,
            'url' => $data['url'] ?? $data['user_url'] ?? null,
            'bio' => $data['bio'] ?? $data['user_bio'] ?? null,
            'timezone' => $data['timezone'] ?? $data['user_timezone'] ?? null,
            'dateFormat' => $data['dateFormat'] ?? $data['user_date_format'] ?? null,
            'timeFormat' => $data['timeFormat'] ?? $data['user_time_format'] ?? null,
            'locale' => $data['locale'] ?? $data['user_locale'] ?? null,
            'registered' => $data['registered'] ?? $data['user_registered'] ?? null,
            'modified' => $data['modified'] ?? $data['user_modified'] ?? null,
            'activationKey' => $data['activationKey'] ?? $data['user_activation_key'] ?? null,
        ];
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
        if (property_exists($this, $key) && $this->{$key} !== null) {
            return true;
        }

        if (array_key_exists($key, $this->attributes)) {
            return true;
        }

        if ($this->id === null) {
            return false;
        }

        return AttributesFactory::user()
            ->exists(get_current_site_id(), $this->id, $key);
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
    public function __get(string $key): mixed
    {
        if (property_exists($this, $key)) {
            return $this->{$key};
        }

        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        if ($this->id === null) {
            return null;
        }

        $value = AttributesFactory::user()
            ->get(
                siteId: get_current_site_id(),
                userId: $this->id,
                key: $key,
            );

        return is_string($value) ? purify_html($value) : $value;
    }

    /**
     * Magic method for setting custom user fields.
     *
     * This method does not update custom fields in the database. It only stores
     * the value on the User instance.
     *
     * @param string $key   User attribute key.
     * @param mixed  $value User attribute value.
     */
    public function __set(string $key, mixed $value): void
    {
        if (property_exists($this, $key)) {
            $this->{$key} = $value === null ? null : (string) $value;
            return;
        }

        $this->attributes[$key] = $value;
    }

    /**
     * Magic method for unsetting a certain custom field.
     *
     * @param string $key User attribute key to unset.
     */
    public function __unset(string $key)
    {
        if (property_exists($this, $key)) {
            $this->{$key} = null;
            return;
        }

        unset($this->attributes[$key]);
    }

    /**
     * Retrieve the value of a property or custom field key.
     *
     * Retrieves from the user and site_user table.
     *
     * @param string $key Property
     * @param mixed|null $default
     * @return string
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function get(string $key, mixed $default = null): string
    {
        $value = $this->__get($key);

        return $value ?? $default;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function has(string $key): bool
    {
        return $this->__isset($key);
    }

    /**
     * Determine whether a property or custom field key is set.
     *
     * Consults the user and site_user tables.
     *
     * @param string $key Property.
     * @return bool
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function isSet(string $key): bool
    {
        return $this->has($key);
    }

    /**
     * Return an array representation.
     *
     * @return array Array representation.
     */
    public function toArray(bool $includePassword = false): array
    {
        $data = [
            'id' => $this->id,
            'login' => $this->login,
            'token' => $this->token,
            'fname' => $this->fname,
            'mname' => $this->mname,
            'lname' => $this->lname,
            'email' => $this->email,
            'url' => $this->url,
            'bio' => $this->bio,
            'timezone' => $this->timezone,
            'dateFormat' => $this->dateFormat,
            'timeFormat' => $this->timeFormat,
            'locale' => $this->locale,
            'registered' => $this->registered,
            'modified' => $this->modified,
            'activationKey' => $this->activationKey,
            'attributes' => $this->attributes,
        ];

        if ($includePassword) {
            $data['pass'] = $this->pass;
        }

        return $data;
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
        if ($this->id === null) {
            throw new Exception('Cannot set a role for a user without an ID.');
        }

        $siteId ??= get_current_site_id();
        $attributes = AttributesFactory::user();

        $oldRole = $attributes->get(
            siteId: $siteId,
            userId: $this->id,
            key: 'role',
            default: ''
        );

        $attributes->set(
            siteId: $siteId,
            userId: $this->id,
            key: 'role',
            value: $role
        );

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
