<?php

declare(strict_types=1);

namespace App\Shared\Helpers;

use App\Application\Devflow;
use App\Domain\Site\Command\AttributeSiteUserCommand;
use App\Domain\Site\Command\CreateSiteCommand;
use App\Domain\Site\Command\DeleteSiteCommand;
use App\Domain\Site\Command\RemoveSiteUserCommand;
use App\Domain\Site\Command\UpdateSiteCommand;
use App\Domain\Site\Model\Site;
use App\Domain\Site\Query\FindSitesQuery;
use App\Domain\Site\SiteError;
use App\Domain\Site\ValueObject\SiteId;
use App\Domain\User\Model\User;
use App\Domain\User\Query\FindMultisiteUniqueUsersQuery;
use App\Domain\User\ValueObject\UserId;
use App\Infrastructure\Persistence\Cache\SiteCachePsr16;
use App\Infrastructure\Persistence\Cache\UserCachePsr16;
use App\Infrastructure\Services\Site\SiteSchema;
use Qubus\Expressive\Database;
use App\Infrastructure\Services\AttributesFactory;
use App\Shared\Services\DateTime;
use App\Shared\Services\Registry;
use App\Shared\Services\Sanitizer;
use App\Shared\Services\SimpleCacheObjectCacheFactory;
use Codefy\CommandBus\Exceptions\CommandCouldNotBeHandledException;
use Codefy\CommandBus\Exceptions\CommandPropertyNotFoundException;
use Codefy\CommandBus\Exceptions\UnresolvableCommandHandlerException;
use Codefy\Domain\Model\EntityNotFoundException;
use Codefy\Framework\Factory\FileLoggerFactory;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
use DateInvalidTimeZoneException;
use PDOException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Error\Error;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Http\Factories\JsonResponseFactory;
use Qubus\Http\Session\SessionException;
use Qubus\Support\DateTime\QubusDateTimeImmutable;
use Qubus\Support\DateTime\QubusDateTimeZone;
use Qubus\ValueObjects\Identity\Ulid;
use Qubus\ValueObjects\StringLiteral\StringLiteral;
use ReflectionException;

use function array_merge;
use function Codefy\Framework\Helpers\app;
use function Codefy\Framework\Helpers\ask;
use function Codefy\Framework\Helpers\command;
use function Codefy\Framework\Helpers\config;
use function Codefy\Framework\Helpers\logger;
use function Codefy\Framework\Helpers\public_path;
use function Codefy\Framework\Helpers\resource_path;
use function crc32;
use function date;
use function file_get_contents;
use function md5;
use function mkdir;
use function Qubus\Security\Helpers\__observer;
use function Qubus\Security\Helpers\esc_html;
use function Qubus\Security\Helpers\esc_html__;
use function Qubus\Security\Helpers\t__;
use function Qubus\Security\Helpers\unslash;
use function Qubus\Support\Helpers\is_false__;
use function Qubus\Support\Helpers\is_null__;
use function Qubus\Support\Helpers\is_true__;
use function sprintf;
use function str_replace;
use function strtotime;

/**
 * Retrieves all sites.
 *
 * @file core/Shared/Helpers/site.php
 * @throws ReflectionException
 * @throws UnresolvableQueryHandlerException
 */
function get_all_sites(): mixed
{
    return ask(new FindSitesQuery());
}

/**
 * Retrieves site data given a site ID or site object.
 *
 * @file core/Shared/Helpers/site.php
 * @param string $field The field to retrieve the site with (id, key or slug).
 * @param string $value A value for $field.
 * @return object|bool
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_site_by(string $field, string $value): false|object
{
    /** @var Site $site */
    $site = Devflow::$PHP->make(name: Site::class);
    $sitedata = $site->findBy($field, $value);

    if (is_false__($sitedata)) {
        return false;
    }

    return $sitedata;
}

/**
 * Checks whether the given site domain exists.
 *
 * @file core/Shared/Helpers/site.php
 * @param string $sitedomain Site domain to check against.
 * @return bool If site domain exists, return true otherwise return false.
 */
function if_site_domain_exists(string $sitedomain): bool
{
    $dfdb = dfdb();

    $site = $dfdb->getVar(
        $dfdb->prepare(
            "SELECT COUNT(*) FROM {$dfdb->basePrefix}site WHERE site_domain = ?",
            [
                $sitedomain
            ]
        )
    );

    return $site > 0;
}

/**
 * Checks whether the given site exists.
 *
 * @file core/Shared/Helpers/site.php
 * @param string $siteDomain Site domain to check against.
 * @param string $sitePath Site path to check against.
 * @return bool If site exists, return true otherwise return false.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function if_site_exists(string $siteDomain, string $sitePath): bool
{
    $dfdb = dfdb();

    $site = $dfdb->getVar(
        $dfdb->prepare(
            "SELECT COUNT(*) FROM {$dfdb->basePrefix}site WHERE site_domain = ? AND site_path = ?",
            [
                $siteDomain,
                $sitePath
            ]
        )
    );

    return $site > 0;
}

/**
 * Adds user meta data for specified site.
 *
 * @access private
 *
 * @file core/Shared/Helpers/site.php
 * @param string $siteId Site ID.
 * @param array $params Parameters to set (assign_id or role).
 * @return bool True if usermete and role is added.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 * @throws InvalidArgumentException
 */
function add_site_usermeta(string $siteId, array $params = []): bool
{
    /** @var User $userdata */
    $userdata = get_userdata($params['assign_id']);
    $data = [
        'bio' => $userdata->bio,
        'status' => $userdata->status,
        'admin.layout' => $userdata->adminLayout <= 0 ? (int) 0 : (int) $userdata->adminLayout,
        'admin.sidebar' => $userdata->adminSidebar <= 0 ? (int) 0 : (int) $userdata->adminSidebar,
        'admin.skin' => $userdata->adminSkin === null ? (string) 'skin-red' : (string) $userdata->adminSkin
    ];
    foreach ($data as $key => $value) {
        update_user_attribute($params['assign_id'], $key, $value);
    }

    $user = new User(dfdb());
    $user->id = $params['assign_id'];
    $user->setRole((string) $params['role']);

    return true;
}

/**
 * Create the needed directories when a new site is created.
 *
 * @access private
 *
 * @file core/Shared/Helpers/site.php
 * @param string $siteId Site ID.
 * @param Site $site Site object.
 * @param bool $update Whether the site is being created or updated.
 * @return bool True on success or false on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 */
function create_site_directories(string $siteId, Site $site, bool $update = false): bool
{
    if (is_true__($update)) {
        return false;
    }

    $site = get_site_by('id', $siteId);
    if (is_false__($site)) {
        return false;
    }

    $key = site_directory_key($site->key);

    mkdir(
        directory: public_path(
            path: 'sites' . Devflow::$PHP::DS . $key .
            Devflow::$PHP::DS . 'uploads' . Devflow::$PHP::DS  . '__optimized__'
        ),
        permissions: 0755,
        recursive: true
    );

    mkdir(
        directory: public_path(
            path: 'sites' . Devflow::$PHP::DS . $key . Devflow::$PHP::DS . '.trash'
        ),
        permissions: 0755,
        recursive: true
    );

    return true;
}

/**
 * Deletes user meta data when site/user is deleted.
 *
 * @access private
 *
 * @file core/Shared/Helpers/site.php
 * @param string $siteId Site Id.
 * @param Site $oldSite Site object.
 * @return bool True on success or false on failure.
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws \Exception
 */
function delete_site_usermeta(string $siteId, Site $oldSite): bool
{
    $dfdb = dfdb();

    if ($siteId !== $oldSite->id) {
        return false;
    }

    try {
        $users = get_users_by_site_key($oldSite->key);
        foreach ($users as $user) {
            $_user = new User($dfdb);
            $_user->id = $user['user_id'];
            $_user->login = $user['user_login'];
            $_user->token = $user['user_token'];
            $_user->email = $user['user_email'];

            UserCachePsr16::clean($_user);
        }

        return true;
    } catch (PDOException | Exception $e) {
        FileLoggerFactory::getLogger()->error(sprintf('ERROR[%s]: %s', $e->getCode(), $e->getMessage()));
    }

    return false;
}

/**
 * Deletes site tables when site is deleted.
 *
 * @access private
 *
 * @file core/Shared/Helpers/site.php
 * @param string $siteId Site ID.
 * @param Site $oldSite Site object.
 * @return bool True on success or false on failure.
 * @throws Exception
 * @throws ReflectionException
 */
function delete_site_tables(string $siteId, Site $oldSite): bool
{
    $dfdb = dfdb();

    if ($siteId !== $oldSite->id) {
        return false;
    }

    $tables = [];
    $sql = $dfdb->getResults(
        $dfdb->prepare(
            "SHOW TABLES LIKE ?",
            [
                "%$oldSite->key%"
            ]
        ),
        Database::ARRAY_A
    );

    foreach ($sql as $value) {
        foreach ($value as $row) {
            $tables[] = $row;
        }
    }

    /**
     * Filters the tables to drop when the site is deleted.
     *
     * @file core/Shared/Helpers/site.php
     * @param array  $tables  Name array of the site tables to be dropped.
     * @param string $siteKey The key of the site to drop tables for.
     */
    $dropTables = __observer()->filter->applyFilter('site.drop.tables', $tables, $oldSite->key);

    try {
        $dfdb->transactional(function () use ($dfdb, $dropTables) {
            $dfdb->getConnection()->pdo->exec(statement: "SET GLOBAL FOREIGN_KEY_CHECKS=0;");

            foreach ((array) $dropTables as $table) {
                $dfdb->getConnection()->pdo->exec(statement: sprintf("DROP TABLE IF EXISTS %s", $table));
            }

            $dfdb->getConnection()->pdo->exec(statement: "SET GLOBAL FOREIGN_KEY_CHECKS=1;");
        });

        return true;
    } catch (PDOException | \Exception $e) {
        FileLoggerFactory::getLogger()->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $e->getCode(),
                $e->getMessage()
            ),
            [
                'Site Function' => 'delete_site_tables'
            ]
        );
    }

    return false;
}

/**
 * Deletes the site directory when the site is deleted.
 *
 * @access private
 *
 * @file core/Shared/Helpers/site.php
 * @param string $siteId Site ID.
 * @return bool True on success or false on failure.
 * @throws TypeException
 */
function delete_site_directories(string $siteId, Site $oldSite): bool
{
    if ($siteId !== $oldSite->id) {
        return false;
    }

    $key = site_directory_key($oldSite->key);

    rmdir__(public_path(path: 'sites' . Devflow::$PHP::DS . $key));

    return true;
}

/**
 * @throws TypeException
 */
function site_directory_key(string $siteKey): int
{
    return crc32(string: $siteKey . config()->string(key: 'cms.app_salt'));
}

/**
 * Retrieve the current site key.
 *
 * @file core/Shared/Helpers/site.php
 * @return mixed Site key.
 * @throws ReflectionException
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 */
function get_current_site_key(): mixed
{
    return Registry::getInstance()->get('siteKey');
}

/**
 * @return string
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_current_site_id(): string
{
    $site = get_site_by(field: 'key', value: get_current_site_key());

    return $site->id;
}

/**
 * Retrieve a list of all users in the system.
 *
 * @file core/Shared/Helpers/site.php
 * @return array Users data.
 * @throws ReflectionException
 * @throws UnresolvableQueryHandlerException
 */
function get_multisite_users(): array
{
    return ask(new FindMultisiteUniqueUsersQuery());
}

/**
 * Add user to a site.
 *
 * @file core/Shared/Helpers/site.php
 * @param string|User $user User to add to a site.
 * @param string|Site $site Site to add user to.
 * @param string $role Role to assign to user for this site.
 * @return false|string User id on success or false on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 */
function add_user_to_site(User|string $user, Site|string $site, string $role): false|string
{
    if ($user instanceof User) {
        $userdata = $user;
    } else {
        $userdata = get_userdata($user);
    }

    if ($site instanceof Site) {
        $sitedata = $site;
    } else {
        $sitedata = get_site_by('id', $site);
    }

    if (!username_exists($userdata->login)) {
        return false;
    }

    if (!if_site_exists($sitedata->domain, $sitedata->path)) {
        return false;
    }

    // Store values to save in user meta.
    $attributes = [];
    $attributes['role'] = $role;
    $attributes['status'] = 'A';
    $attributes['admin.layout'] = 0;
    $attributes['admin.sidebar'] = 0;
    $attributes['admin.skin'] = 'skin-red';

    /**
     * Filters a user's attribute values and keys immediately after the user is added
     * and before any user attribute is inserted.
     *
     * @param array $attributes {
     *     Default attribute values and keys for the user.
     *
     *     @type string $role           The user's role.
     *     @type string $status         The user's status.
     *     @type int    $admin.layout   The user's layout option.
     *     @type int    $admin.sidebar  The user's sidebar option.
     *     @type int    $admin.skin     The user's skin option.
     * }
     * @param Site $sitedata Site object.
     * @param User $userdata User object.
     */
    $attribute = __observer()->filter->applyFilter('add.user.to.site', $attributes, $sitedata, $userdata);

    // Make sure user attribute doesn't already exist for this user.
    if (null === get_user_attribute(userId: $userdata->id, key: 'role', siteId: $sitedata->id)) {
        foreach ($attribute as $key => $value) {
            update_user_attribute(userId: $userdata->id, key: $key, value: $value, siteId: $sitedata->id);
        }
    }

    return $userdata->id;
}

/**
 * Insert a site into the database.
 *
 * Some of the `$sitedata` array fields have filters associated with the values. Exceptions are
 * 'site_owner', 'site_registered' and 'site_modified' The filters have the prefix 'pre.'
 * followed by the field name. An example using 'site_name' would have the filter called,
 * 'pre.site.name' that can be hooked into.
 *
 * @file core/Shared/Helpers/site.php
 * @param array|Site|ServerRequestInterface $sitedata {
 *      An array or Site array of user data arguments.
 *
 * @type string $id Sites's ID. If supplied, the site will be updated.
 * @type string $domain The site's domain.
 * @type string $mapping Mapped domain to use for the site.
 * @type string $name The site's name/title.
 * @type string $path The site's path.
 * @type string $owner The site's owner.
 * @type string $status The site's status.
 * @type string $registered Date the site registered. Format is 'Y-m-d H:i:s'.
 * @type string $modified Date the site's record was updated. Format is 'Y-m-d H:i:s'.
 *  }
 *
 * @return string|Error  The newly created site's id or Error if the site could not
 *                       be created.
 * @throws CommandPropertyNotFoundException
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 * @throws UnresolvableCommandHandlerException
 * @throws DateInvalidTimeZoneException
 */
function cms_insert_site(array|ServerRequestInterface|Site $sitedata): Error|string
{
    if ($sitedata instanceof ServerRequestInterface) {
        $sitedata = $sitedata->getParsedBody();
    } elseif ($sitedata instanceof Site) {
        $sitedata = $sitedata->toArray();
    }

    // Are we updating or creating?
    if (!empty($sitedata['id'])) {
        $update = true;
        $siteId = SiteId::fromString($sitedata['id']);
        $siteKey = $sitedata['key'];

        /** @var Site $siteBefore */
        $siteBefore = get_site_by(field: 'id', value: $siteId->toNative());

        if (is_false__($siteBefore)) {
            return new SiteError(
                esc_html__(
                    string: 'The ID of this entity is invalid.',
                    domain: 'devflow'
                ),
                'invalid_id'
            );
        }
        $previousStatus = get_site_status($siteId->toNative());
        /**
         * Fires immediately before a site is inserted into the site document.
         *
         * @file core/Shared/Helpers/site.php
         * @param string $previousStatus Status of the site before it is created or updated.
         * @param string $siteId         The site's site_id.
         * @param bool   $update         Whether this is an existing site or a new site.
         */
        __observer()->action->doAction('site_previous_status', $previousStatus, $siteId->toNative(), $update);

        /**
         * Create new site object.
         *
         * @var Site $site
         */
        $site = Devflow::$PHP->make(Site::class);
        $site->id = $siteId->toNative();
    } else {
        $update = false;
        $previousStatus = 'new';
        $siteId = new SiteId();
        $siteKey = generate_site_key();
        /**
         * Fires immediately before a site is inserted into the site document.
         *
         * @file core/Shared/Helpers/site.php
         * @param string $previousStatus Status of the site before it is created or updated.
         * @param string $siteId         The site's site_id.
         * @param bool   $update         Whether this is an existing site or a new site.
         */
        __observer()->action->doAction('site_previous_status', $previousStatus, $siteId->toNative(), $update);

        /**
         * Create new site object.
         *
         * @var Site $site
         */
        $site = app(name: Site::class);
        $site->id = $siteId->toNative();
    }
    $site->key = $siteKey;

    /** @var RequestInterface $request */
    $request = app(name: RequestInterface::class);
    $host = $request->getUri()->getHost();

    $rawSiteDomain = isset($sitedata['subdomain']) ?
    trim(strtolower($sitedata['subdomain'])) . '.' . $host :
    trim(strtolower($sitedata['domain']));
    $sanitizedSiteDomain = Sanitizer::item($rawSiteDomain);
    /**
     * Filters a site's domain before the site is created or updated.
     *
     * @file core/Shared/Helpers/site.php
     * @param string $sanitizedSiteDomain Site domain after it has been sanitized
     * @param string $preSiteDomain The site's domain.
     */
    $preSiteDomain = __observer()->filter->applyFilter(
        'pre.site.domain',
        (string) $sanitizedSiteDomain,
        (string) $rawSiteDomain
    );

    $siteDomain = trim($preSiteDomain);

    // site_domain cannot be empty.
    if (empty($siteDomain)) {
        return new SiteError(
            esc_html__(
                string: 'Site domain cannot be empty.',
                domain: 'devflow'
            ),
            'empty_value',
        );
    }

    if (!$update && if_site_domain_exists($siteDomain)) {
        return new SiteError(
            esc_html__(
                string: 'Sorry, that site already exists!',
                domain: 'devflow'
            ),
            'duplicate',
        );
    }
    $site->domain = $siteDomain;

    $rawSiteMapping = $sitedata['mapping'] ?? '';
    $sanitizedSiteMapping = Sanitizer::item($rawSiteMapping);
    /**
     * Filters a site's mapped domain before the site is created or updated.
     *
     * @file core/Shared/Helpers/site.php
     * @param string $sanitizedSiteMapping Mapped domain after it has been sanitized
     * @param string $rawSiteMapping The site's mapping.
     */
    $siteMapping = __observer()->filter->applyFilter(
        'pre.site.mapping',
        (string) $sanitizedSiteMapping,
        (string) $rawSiteMapping
    );
    $site->mapping = $siteMapping;

    $rawSiteName = $sitedata['name'];
    $sanitizedSiteName = Sanitizer::item($rawSiteName);
    /**
     * Filters a site's name before the site is created or updated.
     *
     * @file core/Shared/Helpers/site.php
     * @param string $sanitizedSiteName Site name after it has been sanitized
     * @param string $rawSiteName The site's name.
     */
    $siteName = __observer()->filter->applyFilter(
        'pre.site.name',
        (string) $sanitizedSiteName,
        (string) $rawSiteName
    );
    $site->name = $siteName;

    if (isset($sitedata['slug'])) {
        /**
         * cms_unique_site_slug will take the original slug supplied and check
         * to make sure that it is unique. If not unique, it will make it unique
         * by adding a number at the end.
         */
        $siteSlug = cms_unique_site_slug($sitedata['slug'], $siteName, $siteId->toNative());
    } else {
        /**
         * For an update, don't modify the site_slug if it
         * wasn't supplied as an argument.
         */
        $siteSlug = $siteBefore->slug;
    }

    $rawSiteSlug = $siteSlug;
    $sanitizedSiteSlug = Sanitizer::item($rawSiteSlug);
    /**
     * Filters a site's slug before created/updated.
     *
     * @file core/Shared/Helpers/site.php
     * @param string $sanitizedSiteSlug Site slug after it has been sanitized
     * @param string $rawSiteSlug The site's slug.
     */
    $siteSlug = __observer()->filter->applyFilter(
        'pre.site.slug',
        (string) $sanitizedSiteSlug,
        (string) $rawSiteSlug
    );
    $site->slug = $siteSlug;

    $rawSitePath = $sitedata['path'];
    $sanitizedSitePath = Sanitizer::item($rawSitePath);
    /**
     * Filters a site's path before the site is created or updated.
     *
     * @file core/Shared/Helpers/site.php
     * @param string $sanitizedSitePath Site path after it has been sanitized
     * @param string $rawSitePath The site's path.
     */
    $sitePath = __observer()->filter->applyFilter(
        'pre.site.path',
        (string) $sanitizedSitePath,
        (string) $rawSitePath
    );
    $site->path = $sitePath;

    /*
     * If there is no update, just check for `email_exists`. If there is an update,
     * check if current email and new email are the same, or not, and check `email_exists`
     * accordingly.
     */
    if (
        (!$update ||
                (!empty($siteBefore) &&
                0 !==
                        strcasecmp($siteDomain . $sitePath, $siteBefore->domain . $siteBefore->path))) &&
        if_site_exists($siteDomain, $sitePath)
    ) {
        return new SiteError(
            esc_html__(
                string: 'Sorry, that site domain and path is already used.',
                domain: 'devflow'
            ),
            'duplicate',
        );
    }

    $siteOwner = !isset($sitedata['owner']) ? get_current_user_id() : $sitedata['owner'];
    $site->owner = $siteOwner;

    $rawSiteStatus = !isset($sitedata['status']) ? 'public' : $sitedata['status'];
    $sanitizedSiteStatus = Sanitizer::item($rawSiteStatus);
    /**
     * Filters a site's status before the site is created or updated.
     *
     * @file core/Shared/Helpers/site.php
     * @param string $sanitizedSiteStatus Site status after it has been sanitized
     * @param string $rawSiteStatus The site's status.
     */
    $siteStatus = __observer()->filter->applyFilter(
        'pre.site.status',
        (string) $sanitizedSiteStatus,
        (string) $rawSiteStatus
    );
    $site->status = $siteStatus;

    $siteRegistered = new DateTime()->current(type: 'db');

    $siteModified = new DateTime()->current(type: 'db');

    $compacted = [
        'id' => $siteId->toNative(),
        'key' => $siteKey,
        'name' => $siteName,
        'slug' => $siteSlug,
        'domain' => $siteDomain,
        'mapping' => $siteMapping,
        'path' => $sitePath,
        'owner' => $siteOwner,
        'status' => $siteStatus,
        'registered' => $siteRegistered,
        'modified' => $siteModified,
    ];

    $data = unslash($compacted);

    /**
     * Filters site data before the record is created or updated.
     *
     * @file core/Shared/Helpers/site.php
     * @param array $data {
     *     Values and keys for the site.
     *
     *     @type object $siteId        The site's id
     *     @type string $siteKey       The site's key
     *     @type string $siteDomain    The site's domain
     *     @type string $siteMapping   The site's mapped domain.
     *     @type string $siteName      The site's name/title.
     *     @type string $siteSlug      The site's slug.
     *     @type string $sitePath      The site's path.
     *     @type string $siteOwner     The site's owner.
     *     @type string $siteStatus    The site's status.
     * }
     * @param bool     $update      Whether the site is being updated rather than created.
     * @param string|null $siteId   ID of the site to be updated, or NULL if the site is being created.
     */
    __observer()->filter->applyFilter(
        'cms.pre.insert.site.data',
        $data,
        $update,
        $update ? $siteBefore->id : null
    );

    if ($update) {
        $site->modified = $siteModified;

        try {
            $command = new UpdateSiteCommand([
                'id' => SiteId::fromString($site->id),
                'name' => new StringLiteral($site->name),
                'slug' => new StringLiteral($site->slug),
                'domain' => new StringLiteral($site->domain),
                'mapping' => new StringLiteral($site->mapping),
                'path' => new StringLiteral($site->path),
                'owner' => UserId::fromString($site->owner),
                'status' => new StringLiteral($site->status),
                'modified' => QubusDateTimeImmutable::createFromDate(
                    date('Y', strtotime($site->modified)),
                    date('m', strtotime($site->modified)),
                    date('d', strtotime($site->modified)),
                    new QubusDateTimeZone(get_option(key: 'site_timezone'))
                ),
            ]);

            command($command);

        } catch (PDOException $e) {
            FileLoggerFactory::getLogger()->error(
                sprintf(
                    'SQLSTATE[%s]: %s',
                    $e->getCode(),
                    $e->getMessage()
                ),
                [
                    'Site Function' => 'cms_insert_site'
                ]
            );
        }
    } else {
        $site->registered = $siteRegistered;

        try {
            $command = new CreateSiteCommand([
                'id' => SiteId::fromString($site->id),
                'key' => new StringLiteral($site->key),
                'name' => new StringLiteral($site->name),
                'slug' => new StringLiteral($site->slug),
                'domain' => new StringLiteral($site->domain),
                'mapping' => new StringLiteral($site->mapping),
                'path' => new StringLiteral($site->path),
                'owner' => UserId::fromString($site->owner),
                'status' => new StringLiteral($site->status),
                'registered' => QubusDateTimeImmutable::createFromDate(
                    date('Y', strtotime($site->registered)),
                    date('m', strtotime($site->registered)),
                    date('d', strtotime($site->registered)),
                    new QubusDateTimeZone(get_option(key: 'site_timezone'))
                ),
            ]);

            command($command);

        } catch (
            PDOException |
            CommandCouldNotBeHandledException |
            UnresolvableCommandHandlerException |
            ReflectionException $e
        ) {
            FileLoggerFactory::getLogger()->error(
                sprintf(
                    'SQLSTATE[%s]: %s',
                    $e->getCode(),
                    $e->getMessage()
                ),
                [
                    'Site Function' => 'cms_insert_site'
                ]
            );
        }
    }

    /** @var Site $site */
    $site = get_site_by('id', $siteId->toNative());

    if ($update) {
        /**
         * Fires immediately after an existing site is updated.
         *
         * @file core/Shared/Helpers/site.php
         * @param string $siteId   Site ID.
         * @param Site $site       Site data object.
         */
        __observer()->action->doAction('update_site', $siteId->toNative(), $site);

        /** @var Site $siteAfter */
        $siteAfter = get_site_by('id', $siteId->toNative());

        /**
         * Action hook triggered after existing site has been updated.
         *
         * @file core/Shared/Helpers/site.php
         * @param string $siteId    Site id.
         * @param Site $siteAfter   Site object following the update.
         * @param Site $siteBefore  Site object before the update.
         */
        __observer()->action->doAction('site_updated', $siteId->toNative(), $siteAfter, $siteBefore);
    }

    /**
     * Fires immediately after a new site is saved.
     *
     * @file core/Shared/Helpers/site.php
     * @param string $siteId Site ID.
     * @param Site $site     Site object.
     * @param bool $update   Whether this is an existing site or a new site.
     */
    __observer()->action->doAction('save_site', $siteId->toNative(), $site, $update);

    /**
     * Action hook triggered after site has been saved.
     *
     * The dynamic portion of this hook, `$siteStatus`,
     * is the site's status.
     *
     * @file core/Shared/Helpers/site.php
     * @param string $siteId    The site's id.
     * @param Site $site       Site object.
     * @param bool $update     Whether this is an existing site or a new site.
     */
    __observer()->action->doAction("save_site_{$siteStatus}", $siteId->toNative(), $site, $update);

    /**
     * Action hook triggered after site has been saved.
     *
     * @file core/Shared/Helpers/site.php
     * @param string $siteId   The site's id.
     * @param Site $site       Site object.
     * @param bool $update     Whether this is an existing site or a new site.
     */
    __observer()->action->doAction('cms_after_insert_site_data', $siteId->toNative(), $site, $update);

    return $siteId->toNative();
}

/**
 * Update a site in the database.
 *
 * See cms_insert_site() For what fields can be set in $sitedata.
 *
 * @file core/Shared/Helpers/site.php
 * @param array|ServerRequestInterface|Site $sitedata An array of site data or a site object.
 * @return Error|string The updated site's id or Error if update failed.
 * @throws CommandPropertyNotFoundException
 * @throws ContainerExceptionInterface
 * @throws DateInvalidTimeZoneException
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 * @throws UnresolvableCommandHandlerException
 */
function cms_update_site(array|ServerRequestInterface|Site $sitedata): Error|string
{
    if ($sitedata instanceof ServerRequestInterface) {
        $sitedata = $sitedata->getParsedBody();
    } elseif ($sitedata instanceof Site) {
        $sitedata = $sitedata->toArray();
    }

    $dfdb = dfdb();

    $details = $dfdb->getRow(
        $dfdb->prepare(
            "SELECT site_owner FROM {$dfdb->basePrefix}site WHERE site_id = ?",
            [
                $sitedata['id']
            ]
        ),
        Database::ARRAY_A
    );
    if ($details['site_owner'] !== $sitedata['owner']) {
        $ownerChange = true;
    } else {
        $ownerChange = false;
    }

    $id = $sitedata['id'] ?? '';
    if ('' === $id) {
        return new SiteError(
            esc_html__(
                string: 'The ID of this entity is invalid.',
                domain: 'devflow'
            ),
            'invalid_id',
        );
    }

    $siteId = cms_insert_site($sitedata);

    /**
     * If the site admin has changed, delete the site user record of the old admin
     * and add a site user record for the new admin.
     */
    if (!is_null__($siteId) && $ownerChange) {
        delete_site_user_record($siteId, $details['site_owner']);
        add_user_to_site(user: $sitedata['owner'], site: $sitedata['id'], role: 'admin');
    }

    SiteCachePsr16::clean($sitedata);

    return $siteId;
}

/**
 * Deletes a site.
 *
 * @file core/Shared/Helpers/site.php
 * @param string $siteId ID of site to delete.
 * @return string|Error Returns id of deleted site or Error.
 * @throws CommandPropertyNotFoundException
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 * @throws UnresolvableCommandHandlerException
 */
function cms_delete_site(string $siteId): Error|string
{
    /** @var Site $oldSite */
    $oldSite = get_site_by('id', $siteId);

    if (is_false__($oldSite)) {
        return new SiteError(
            esc_html__(
                string: 'Site does not exist.',
                domain: 'devflow'
            ),
            code: 'not_found'
        );
    }

    /**
     * Action hook triggered before the site is deleted.
     *
     * @file core/Shared/Helpers/site.php
     * @param string $id      Site ID.
     * @param Site   $oldSite Data object of site to be deleted.
     */
    __observer()->action->doAction('delete_site', $siteId, $oldSite);

    try {
        command(
            new DeleteSiteCommand([
                'id' => SiteId::fromString($siteId)
            ])
        );
    } catch (PDOException $e) {
        FileLoggerFactory::getLogger()->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $e->getCode(),
                $e->getMessage()
            ),
            [
                'Site Function' => 'cms_delete_site'
            ]
        );
    }

    /**
     * Action hook triggered after the site is deleted.
     *
     * @file core/Shared/Helpers/site.php
     * @param string $id    Site ID.
     * @param Site $oldSite Site object that was deleted.
     */
    __observer()->action->doAction('deleted_site', $siteId, $oldSite);

    SiteCachePsr16::clean($oldSite);

    return $siteId;
}

/**
 * Deletes a user from the entire system.
 *
 * @file core/Shared/Helpers/site.php
 * @param string $userId The id of user to be deleted.
 * @param array $params User parameters (assign_id and role).
 * @return bool|Error Returns true if successful or returns error otherwise.
 * @throws CommandPropertyNotFoundException
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws SessionException
 * @throws TypeException
 * @throws UnresolvableQueryHandlerException
 * @throws \Exception
 */
function cms_delete_site_user(string $userId, array $params = []): Error|bool
{
    $dfdb = dfdb();

    if ('' === $userId) {
        return false;
    }

    /** @var User $user */
    $user = get_userdata($userId);
    if (is_false__($user)) {
        return false;
    }

    if ($user->role === 'super') {
        Devflow::$PHP->flash->error(
            esc_html__(
                string: 'You are not allowed to delete a super administrator account.',
                domain: 'devflow'
            )
        );
        return false;
    }

    /** @var Site[] $sites */
    $sites = get_owner_sites($userId);

    if (isset($params['assign_id']) && !is_null__($params['assign_id']) && 'null' !== $params['assign_id']) {
        /** @var User $assignUser */
        $assignUser = get_userdata($params['assign_id']);
        /**
         * Clean cache of the assigned user.
         */
        SimpleCacheObjectCacheFactory::make(namespace: 'users')->delete(key: md5($assignUser->id));
        /**
         * We need to reassign the site(s) to the selected user and create the
         * needed site_user record.
         */
        if (!empty($sites)) {
            foreach ($sites as $site) {
                SimpleCacheObjectCacheFactory::make(namespace: 'sites')->delete(key: md5($site->id));

                add_user_to_site($params['assign_id'], $site->id, $params['role']);

                $params = array_merge(['site_id' => $site->id], $params);

                /**
                 * Sites will be reassigned before the user is deleted.
                 *
                 * @file core/Shared/Helpers/site.php
                 * @param string $userId  ID of user to be deleted.
                 * @param array $params   User and site parameters (assign_id, role and site_id).
                 */
                __observer()->action->doAction('reassign_sites', $userId, $params);
            }
        }
    } else {
        if (!empty($sites)) {

            try {
                $dfdb->transactional(function () use ($dfdb, $userId) {
                    $dfdb
                        ->table(tableName: $dfdb->basePrefix . 'site')
                        ->where(condition: 'site_owner = ?', parameters: $userId)
                        ->delete();
                });
            } catch (PDOException $e) {
                return new SiteError($e->getCode(), t__(msgid: 'Site deletion exception occurred.', domain: 'devflow'));
            }

            foreach ($sites as $oldSite) {
                /** @var Site $site */
                $site = Devflow::$PHP->make(name: Site::class);
                $site->create((array) $oldSite);
                SimpleCacheObjectCacheFactory::make(namespace: 'sites')->delete(key: md5($site->id));
                /**
                 * Action hook triggered after the site is deleted.
                 *
                 * @param string  $siteId Site ID.
                 * @param Site    $site   Site object that was deleted.
                 */
                __observer()->action->doAction('deleted_site', $site->id, $site);
            }
        }
    }

    /**
     * Action hook fires immediately before a user is deleted from the system.
     *
     * @file core/Shared/Helpers/site.php
     * @param string $userId ID of the user to delete.
     * @param array $params  User parameters (assign_id and role).
     */
    __observer()->action->doAction('delete_site_user', $userId, $params);

    /**
     * Finally delete the user from the system.
     */
    try {
        $dfdb->transactional(function () use ($dfdb, $userId) {
            $dfdb
                ->table(tableName: $dfdb->basePrefix . 'user')
                ->where(condition: 'user_id = ?', parameters: $userId)
                ->delete();

            $dfdb
                ->table(tableName: $dfdb->basePrefix . 'site_user')
                ->where(condition: 'user_id = ?', parameters: $userId)
                ->delete();
        });
    } catch (PDOException $e) {
        return new SiteError(
            sprintf(
                'ERROR[%s]: %s',
                $e->getCode(),
                t__(msgid: 'User delete exception occurred.', domain: 'devflow')
            )
        );
    }

    /**
     * Clear the cache of the deleted user.
     */
    SimpleCacheObjectCacheFactory::make(namespace: 'users')->delete(key: md5($user->id));

    /**
     * Action hook fires immediately after a user has been deleted from the system.
     *
     * @file core/Shared/Helpers/site.php
     * @param string $userId   ID of the user who was deleted.
     * @param array $params    User parameters (assign_id and role).
     */
    __observer()->action->doAction('deleted_site_user', $userId, $params);

    return true;
}

/**
 * @param string $userId
 * @param array{site_id:string, assign_id:string|null, role:string|null} $params
 * @return void
 * @throws ContainerExceptionInterface
 * @throws EntityNotFoundException
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 * @throws \Exception
 */
function remove_user_from_site(string $userId, array $params = []): void
{
    /** @var Site $site */
    $site = get_site_by(field: 'id', value: $params['site_id']);
    if(is_false__($site)) {
        throw new EntityNotFoundException(
            esc_html__(
                string: sprintf('The site with ID %s does not exist.', $params['site_id']),
                domain: 'devflow'
            )
        );
    }

    /** @var User $oldUser */
    $oldUser = get_userdata($userId);
    if (is_false__($oldUser)) {
        throw new EntityNotFoundException(
            esc_html__(
                string: sprintf('The user with ID %s does not exist.', $userId),
                domain: 'devflow'
            )
        );
    }

    if ($oldUser->role === 'super') {
        throw new \Exception(
            esc_html__(
                string: 'You are not allowed to remove super admins from this site.',
                domain: 'devflow'
            )
        );
    }

    try {
        command(
            new RemoveSiteUserCommand([
                'siteId' => $site->id,
                'userId' => $oldUser->id,
            ])
        );

        // If assign_id is set, then reassign content to this user.
        if (isset($params['assign_id']) && !is_null__($params['assign_id']) && 'null' !== $params['assign_id']) {
            add_user_to_site($params['assign_id'], $site->id, $params['role']);

            command(
                new AttributeSiteUserCommand([
                    'siteId' => SiteId::fromNative($site->id),
                    'authorId' => UserId::fromNative($oldUser->id),
                    'assignId' => UserId::fromNative($params['assign_id']),
                ])
            );
        }
    } catch (CommandPropertyNotFoundException|UnresolvableCommandHandlerException|ReflectionException $e) {
        logger(level: 'error', message: $e->getMessage());
    }
}

/**
 * Creates new tables and user meta for site admin after new site
 * is created.
 *
 * @access private Used when the action hook `save_site` is called.
 *
 * @file core/Shared/Helpers/site.php
 * @param string $siteId Site id of the newly created site.
 * @param Site $site Site object of newly created site.
 * @param bool $update Whether the site is being created or updated.
 * @return string|bool Returns the site id if successful or false on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 * @throws \Exception
 */
function new_site_schema(string $siteId, Site $site, bool $update): bool|string
{
    $dfdb = dfdb();

    if ($update) {
        return false;
    }

    /** @var Site $site */
    $site = get_site_by('id', $siteId);

    if (!$site) {
        return false;
    }

    /** @var User $userdata */
    $userdata = get_userdata($site->owner);

    $apiKey = generate_unique_key(length: 20);
    $sitePrefix = $site->key;

    $schema = new SiteSchema($dfdb, $sitePrefix);
    $schema->eventStore();
    $schema->content();
    $schema->option();
    $schema->plugin();
    $schema->product();
    $schema->elfinderFile();
    $schema->elfinderTrash();
    $schema->pages();
    $schema->uploads();
    $schema->pageTranslations();
    $schema->settings();

    $insertData = file_get_contents(resource_path(path: 'tpl/option_table_insert.tpl'));
    $insertData = str_replace('{ulid_1}', Ulid::generateAsString(), $insertData);
    $insertData = str_replace('{ulid_2}', Ulid::generateAsString(), $insertData);
    $insertData = str_replace('{ulid_3}', Ulid::generateAsString(), $insertData);
    $insertData = str_replace('{ulid_4}', Ulid::generateAsString(), $insertData);
    $insertData = str_replace('{ulid_5}', Ulid::generateAsString(), $insertData);
    $insertData = str_replace('{ulid_6}', Ulid::generateAsString(), $insertData);
    $insertData = str_replace('{ulid_7}', Ulid::generateAsString(), $insertData);
    $insertData = str_replace('{ulid_8}', Ulid::generateAsString(), $insertData);
    $insertData = str_replace('{ulid_9}', Ulid::generateAsString(), $insertData);
    $insertData = str_replace('{ulid_10}', Ulid::generateAsString(), $insertData);
    $insertData = str_replace('{ulid_11}', Ulid::generateAsString(), $insertData);
    $insertData = str_replace('{ulid_12}', Ulid::generateAsString(), $insertData);
    $insertData = str_replace('{timezone}', config()->string(key: 'app.timezone'), $insertData);
    $insertData = str_replace('{sitename}', $site->name, $insertData);
    $insertData = str_replace('{admin_email}', $userdata->email, $insertData);
    $insertData = str_replace('{api_key}', $apiKey, $insertData);

    try {
        $dfdb->getConnection()->pdo->exec($insertData);
    } catch (PDOException $e) {
        logger(
            'error',
            sprintf(
                'SQLSTATE[new_site]: %s',
                $e->getMessage()
            ),
            [
                'Site Function' => 'new_site_schema'
            ]
        );
    }

    AttributesFactory::user()->createIfMissing($site->id, $userdata->id);

    // Store values to save in user_attribute.
    $attributes = [];
    $attributes['role'] = 'admin';
    $attributes['status'] = 'A';
    $attributes['admin.layout'] = 0;
    $attributes['admin.sidebar'] = 0;
    $attributes['admin.skin'] = 'skin-red';
    /**
     * Filters a user's attribute values and keys immediately after a new
     * site user record is added.
     *
     * @file core/Shared/Helpers/site.php
     * @param array $attributes {
     *     Default attribute values and keys for the user.
     *
     *     @type string $role           The user's role.
     *     @type string $status         The user's status.
     *     @type int    $admin.layout   The user's layout option.
     *     @type int    $admin.sidebar  The user's sidebar option.
     *     @type int    $admin.skin     The user's skin option.
     * }
     * @param object $userdata   User object.
     */
    $attributes = __observer()->filter->applyFilter('new.site.user.attributes', $attributes, $userdata);
    // Add user attributes.
    foreach ($attributes as $key => $value) {
        update_user_attribute(userId: $userdata->id, key: $key, value: $value, siteId: $site->id);
    }

    return $site->id;
}

/**
 * Adds status label for site's table.
 *
 * @file core/Shared/Helpers/site.php
 * @param string $status Status to check for.
 * @return string Site's status.
 * @throws Exception
 */
function cms_site_status_label(string $status): string
{
    $label = [
        'public' => 'label-success',
        'archive' => 'label-danger'
    ];

    /**
     * Filters the label result.
     */
    return __observer()->filter->applyFilter('site.status.label', $label[$status], $status);
}

/**
 * Checks if site exists or is archived.
 *
 * @access private
 *
 * @file core/Shared/Helpers/site.php
 * @return ResponseInterface
 * @throws Exception
 * @throws \Exception
 */
function does_site_exist(): \Psr\Http\Message\ResponseInterface
{
    $dfdb = dfdb();

    $baseUrl = site_url();
    $sitePath = str_replace('index.php', '', app(ServerRequestInterface::class)['PHP_SELF']);
    $siteDomain = str_replace(['http://', 'https://', $sitePath], '', $baseUrl);

    $site = $dfdb->getCol(
        $dfdb->prepare(
            "SELECT site_status FROM {$dfdb->basePrefix}site WHERE site_domain = ? AND site_path = ?",
            [
                $siteDomain,
                $sitePath
            ]
        )
    );

    if (is_null__($site)) {
        return JsonResponseFactory::create(data: 'Not found.', status: 404);
    }

    if (esc_html($site['site_status']) === 'archive') {
        return JsonResponseFactory::create(data: 'Site unavailable.', status: 503);
    }

    return JsonResponseFactory::create(data: 'Not found.', status: 404);
}

/**
 * A function which retrieves cms site name.
 *
 * Purpose of this function is for the `site_name`
 * filter.
 *
 * @file core/Shared/Helpers/site.php
 * @param string $siteId
 * @return string Site's name on success or '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 */
function get_site_name(string $siteId): string
{
    /** @var Site $site */
    $site = get_site_by('id', $siteId);

    if (!$site) {
        return '';
    }

    $name = $site->name;
    /**
     * Filters the site name.
     *
     * @file core/Shared/Helpers/site.php
     * @param string    $name The site's name.
     * @param string    $siteId The site ID.
     */
    return __observer()->filter->applyFilter('site.name', $name, $siteId);
}

/**
 * A function which retrieves cms site domain.
 *
 * Purpose of this function is for the `site_domain`
 * filter.
 *
 * @file core/Shared/Helpers/site.php
 * @param string $siteId The unique id of a site.
 * @return string Site's domain on success or '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 */
function get_site_domain(string $siteId): string
{
    /** @var Site $site */
    $site = get_site_by('id', $siteId);

    if (!$site) {
        return '';
    }

    $domain = $site->domain;
    /**
     * Filters the site domain.
     *
     * @file core/Shared/Helpers/site.php
     * @param string    $domain The site's domain.
     * @param string    $siteId The site ID.
     */
    return __observer()->filter->applyFilter('site.domain', $domain, $siteId);
}

/**
 * A function which retrieves cms site path.
 *
 * Purpose of this function is for the `site_path`
 * filter.
 *
 * @file core/Shared/Helpers/site.php
 * @param string $siteId The unique id of a site.
 * @return string Site's path on success or false on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 */
function get_site_path(string $siteId): string
{
    /** @var Site $site */
    $site = get_site_by('id', $siteId);

    if (!$site) {
        return '';
    }

    $path = $site->path;
    /**
     * Filters the site path.
     *
     * @file core/Shared/Helpers/site.php
     * @param string    $path The site's path.
     * @param string    $siteId The site ID.
     */
    return __observer()->filter->applyFilter('site.path', $path, $siteId);
}

/**
 * A function which retrieves cms site owner.
 *
 * Purpose of this function is for the `site_owner`
 * filter.
 *
 * @file core/Shared/Helpers/site.php
 * @param string $siteId The unique id of a site.
 * @return string Site's owner on success or false on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 */
function get_site_owner(string $siteId): string
{
    /** @var Site $site */
    $site = get_site_by('id', $siteId);

    if (!$site) {
        return '';
    }

    $owner = $site->owner;
    /**
     * Filters the site owner.
     *
     * @file core/Shared/Helpers/site.php
     * @param string    $owner The site's owner.
     * @param string    $siteId The site ID.
     */
    return __observer()->filter->applyFilter('site.owner', $owner, $siteId);
}

/**
 * A function which retrieves cms site status.
 *
 * Purpose of this function is for the `site_status`
 * filter.
 *
 * @file core/Shared/Helpers/site.php
 * @param string $siteId The unique id of a site.
 * @return string Site's status on success or false on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 */
function get_site_status(string $siteId): string
{
    /** @var Site $site */
    $site = get_site_by('id', $siteId);

    if (!$site) {
        return '';
    }

    $status = $site->status;
    /**
     * Filters the site status.
     *
     * @file core/Shared/Helpers/site.php
     * @param string    $status The site's status.
     * @param string    $siteId The site ID.
     */
    return __observer()->filter->applyFilter('site.status', $status, $siteId);
}

/**
 * Creates a unique site slug.
 *
 * @file core/Shared/Helpers/site.php
 * @param string $originalSlug Original slug of site.
 * @param string $originalTitle Original title of site.
 * @param string $siteId Unique site id.
 * @return string Unique site slug.
 * @throws Exception
 * @throws ReflectionException
 */
function cms_unique_site_slug(string $originalSlug, string $originalTitle, string $siteId): string
{
    if (if_site_slug_exists($siteId, $originalSlug)) {
        $siteSlug = cms_slugify($originalTitle, 'site');
    } else {
        $siteSlug = $originalSlug;
    }
    /**
     * Filters the unique site slug before returned.
     *
     * @file core/Shared/Helpers/site.php
     * @param string $siteSlug      Unique site slug.
     * @param string $originalSlug  The site's original slug.
     * @param string $originalTitle The site's original title before slugified.
     * @param string $siteId        The site's unique id.
     */
    return __observer()->filter->applyFilter(
        'cms.unique.site.slug',
        $siteSlug,
        $originalSlug,
        $originalTitle,
        $siteId
    );
}

/**
 * Retrieves raw info about current site.
 *
 * @file core/Shared/Helpers/site.php
 * @param string $show
 * @param string $filter
 * @return string
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws ReflectionException
 */
function get_siteinfo(string $show = '', string $filter = 'raw'): string
{
    $dispatch = [
        'homeurl' => home_url(),
        'siteurl' => site_url(),
        'description' => get_option(key: 'site_description'),
        'sitename' => get_option(key: 'sitename'),
        'timezone' => get_option(key: 'site_timezone'),
        'admin_email' => get_option(key: 'admin_email'),
        'locale' => get_option(key: 'site_locale'),
        'release' => Devflow::release(),
    ];

    $output = $show === '' ? $dispatch['sitename'] : $dispatch[$show];

    $url = true;
    if (
        !str_contains($show, 'url') &&
        !str_contains($show, 'home')
    ) {
        $url = false;
    }

    if ('display' === $filter) {
        if ($url) {
            /**
             * Filters the URL returned by get_siteinfo().
             *
             * @file core/Shared/Helpers/site.php
             * @param mixed $output The URL returned by siteinfo().
             * @param mixed $show   Type of information requested.
             */
            $output = __observer()->filter->applyFilter('siteinfo.url', $output, $show);
        } else {
            /**
             * Filters the site information returned by get_siteinfo().
             *
             * @file core/Shared/Helpers/site.php
             * @param mixed $output The requested non-URL site information.
             * @param mixed $show   Type of information requested.
             */
            $output = __observer()->filter->applyFilter('siteinfo', $output, $show);
        }
    }

    return $output;
}

/**
 * Retrieves filtered info about current site.
 *
 * @file core/Shared/Helpers/site.php
 * @param string $show
 * @return string
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws ReflectionException
 */
function siteinfo(string $show = ''): string
{
    return get_siteinfo($show, 'display');
}

/**
 * Switches the current site.
 *
 * @param string|null $siteKey
 * @return bool
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function switch_to_site(?string $siteKey = null): bool
{
    $stacked = [];

    $prevSiteKey = get_current_site_key();

    if (is_null__($siteKey)) {
        $siteKey = $prevSiteKey;
    }

    $stacked[] = $prevSiteKey;

    Registry::getInstance()->set('switched_stack', $stacked);

    if ($siteKey === $prevSiteKey) {
        __observer()->action->doAction('switch_site', $siteKey, $prevSiteKey, 'switch');

        Registry::getInstance()->set('switched', true);

        return true;
    }

    dfdb()->setSiteKey($siteKey);
    Registry::getInstance()->set('tblPrefix', dfdb()->getSitePrefix());
    Registry::getInstance()->set('siteKey', $siteKey);

    __observer()->action->doAction('switch_site', $siteKey, $prevSiteKey, 'switch');

    Registry::getInstance()->set('switched', true);

    return true;
}

/**
 * Restores the current site, after calling switch_to_site().
 *
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 * @throws Exception
 * @throws ReflectionException
 * @return bool True on success or if we're already on the current site.
 */
function restore_current_site(): bool
{
    if (empty(Registry::getInstance()->get('switched_stack'))) {
        return false;
    }

    $arrayStack = Registry::getInstance()->get('switched_stack');
    $siteKey = array_pop($arrayStack);
    $prevSiteKey = get_current_site_key();

    if ($siteKey === $prevSiteKey) {
        __observer()->action->doAction('switch_site', $siteKey, $prevSiteKey, 'restore');

        // If we still have items in the switched stack, consider ourselves still 'switched'.
        Registry::getInstance()->set('switched', !empty($arrayStack));

        return true;
    }

    dfdb()->setSiteKey($siteKey);
    Registry::getInstance()->set('siteKey', $siteKey);
    Registry::getInstance()->set('tblPrefix', dfdb()->getSitePrefix());

    __observer()->action->doAction('switch_site', $siteKey, $prevSiteKey, 'restore');

    // If we still have items in the switched stack, consider ourselves still 'switched'.
    Registry::getInstance()->set('switched', !empty($arrayStack));

    return true;
}

/**
 * Determines if site switching is in effect.
 *
 * @throws ReflectionException
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 */
function site_switching_in_effect(): bool
{
    return Registry::getInstance()->has(id: 'switched_stack')
    && !empty(Registry::getInstance()->get(id: 'switched_stack'));
}
