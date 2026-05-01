<?php

declare(strict_types=1);

namespace App\Shared\Helpers;

use App\Application\Devflow;
use App\Domain\Content\Model\Content;
use App\Domain\Content\Query\FindContentQuery;
use App\Domain\Product\Model\Product;
use App\Domain\Product\Query\FindProductsQuery;
use App\Domain\Site\Command\UpdateSiteOwnerCommand;
use App\Domain\Site\Model\Site;
use App\Domain\Site\Query\FindSitesByOwnerQuery;
use App\Domain\Site\ValueObject\SiteId;
use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\UserId;
use Qubus\Expressive\Database;
use App\Infrastructure\Services\Options;
use App\Shared\Services\Sanitizer;
use App\Shared\Services\SimpleCacheObjectCacheFactory;
use Codefy\CommandBus\Exceptions\CommandPropertyNotFoundException;
use Codefy\CommandBus\Exceptions\UnresolvableCommandHandlerException;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception\BadFormatException;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
use Defuse\Crypto\Key;
use PDOException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Http\Session\SessionException;
use Qubus\NoSql\Node;
use Qubus\Support\Collection\ArrayCollection;
use Qubus\Support\Collection\Collection;
use Qubus\Support\DateTime\QubusDateTimeImmutable;
use Qubus\Support\Inflector;
use ReflectionException;

use function Codefy\Framework\Helpers\app;
use function Codefy\Framework\Helpers\ask;
use function Codefy\Framework\Helpers\command;
use function Codefy\Framework\Helpers\config;
use function Codefy\Framework\Helpers\logger;
use function Codefy\Framework\Helpers\mail;
use function Codefy\Framework\Helpers\storage_path;
use function Codefy\Framework\Helpers\trans;
use function Codefy\Framework\Helpers\trans_html;
use function count;
use function in_array;
use function md5;
use function Qubus\Security\Helpers\__observer;
use function Qubus\Security\Helpers\esc_html;
use function Qubus\Support\Helpers\is_false__;
use function Qubus\Support\Helpers\is_null__;
use function sprintf;
use function strtolower;

/**
 * Global database function.
 *
 * @return Database
 */
function dfdb(): Database
{
    static $dfdb;

    if (is_null__($dfdb)) {
        $dfdb = app(name: Database::class);
    }

    return $dfdb;
}

/**
 * Return Options instance.
 *
 * @return Options
 */
function option(): Options
{
    return app(name: Options::class);
}

/**
 * @param string $key
 * @param mixed $default
 * @return mixed
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws ReflectionException
 */
function get_option(string $key, mixed $default = ''): mixed
{
    static $option;

    if(is_null__($option)) {
        $option = option();
    }

    return $option->read(optionKey: $key, default: $default);
}

/**
 * @param string $key
 * @param mixed $value
 * @return mixed
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws ReflectionException
 * @throws TypeException
 */
function update_option(string $key, mixed $value): bool
{
    static $option;

    if(is_null__($option)) {
        $option = option();
    }

    return $option->update(optionKey: $key, newvalue: $value);
}

/**
 * @param string $key
 * @return bool
 * @throws \Exception
 */
function delete_option(string $key): bool
{
    static $option;

    if(is_null__($option)) {
        $option = option();
    }

    return $option->delete(name: $key);
}

/**
 * Returns the object subtype for a given array ID of a specific type.
 *
 * @file core/Shared/Helpers/db.php
 * @param string $type Type of array to request attribute for. (e.g. content, user, product).
 * @param string $id ID of the array to retrieve its subtype.
 * @return string The array subtype or an empty string if unspecified subtype.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_object_subtype(string $type, string $id): string
{
    $objectSubtype = '';

    if ($type === 'content') {
        /** @var Content $content */
        $content = get_content_by_id($id);
        if (is_false__($content)) {
            return '';
        }
        $objectSubtype = 'content';
    } elseif ($type === 'user') {
        /** @var User $user */
        $user = get_user_by(field: 'id', value: $id);
        if (is_false__($user)) {
            return '';
        }
        $objectSubtype = 'user';
    } elseif ($type === 'site') {
        /** @var Site $site */
        $site = get_site_by('id', $id);
        if (is_false__($site)) {
            return '';
        }
        $objectSubtype = 'site';
    } elseif ($type === 'product') {
        /** @var Product $product */
        $product = get_product_by('id', $id);
        if (is_false__($product)) {
            return '';
        }
        $objectSubtype = 'product';
    }

    return __observer()->filter->applyFilter("get.object.subtype.{$type}", $objectSubtype, $id);
}

/**
 * Creates unique slug based on string.
 *
 * @file core/Shared/Helpers/db.php
 * @param string $title Text to be slugified.
 * @param string $table Table the text is saved to (i.e. content, contenttype, site, product)
 * @return string Slug.
 * @throws Exception
 * @throws ReflectionException
 */
function cms_slugify(string $title, string $table): string
{
    $dfdb = dfdb();

    $sanitizedTable = Sanitizer::item($table);
    /**
     * Instantiate the slugify class.
     */
    $slug = Inflector::slugify($title);
    /**
     * Slug field to filter by based on table
     * being called.
     */
    $field = "{$sanitizedTable}_slug";
    $titles = [];
    /**
     * Query content/content_type/site/product.
     */
    if ($sanitizedTable === 'site') {
        $table = $dfdb->basePrefix . $sanitizedTable;
    } else {
        $table = $dfdb->prefix . $sanitizedTable;
    }

    $slugVar = "$slug%";
    $sql = "SELECT *"
    . " FROM ?"
    . " WHERE ? LIKE ?";

    $results = $dfdb->getResults(
        $dfdb->prepare($sql, [$table, $field, $slugVar]),
        Database::ARRAY_A
    );
    if (count($results) > 0) {
        foreach ($results as $item) {
            $titles[] = esc_html($item["$field"]);
        }
    }
    $total = count($titles);
    $last = end($titles);
    /**
     * No equal results, return $slug
     */
    if ($total === 0) {
        return $slug;
    } elseif ($total === 1) { // If we have only one result, we look if it has a number at the end.
        /**
         * Take the only value of the array, because there is only 1.
         */
        $exists = $titles[0];
        /**
         * Kill the slug and see what happens
         */
        $exists = str_replace($slug, "", $exists);
        /**
         * If there is no light about, there was no number at the end.
         * We added it now
         */
        if ("" === trim($exists)) {
            return $slug . "-1";
        } else { // If not..........
            /**
             * Obtain the number because of REGEX it will be there... ;-)
             */
            $number = str_replace("-", "", $exists);
            /**
             * Number plus one.
             */
            $number++;
            return $slug . "-" . $number;
        }
    } else { // If there is more than one result, we need the last one.
        /**
         * Last value
         */
        $exists = $last;
        /**
         * Delete the actual slug and see what happens
         */
        $exists = str_replace($slug, "", $exists);
        /**
         * Obtain the number, easy.
         */
        $number = str_replace("-", "", $exists);
        /**
         * Increment number +1
         */
        $number++;
        return $slug . "-" . $number;
    }
}

/**
 * Returns a list of internal links for TinyMCE.
 *
 * @file core/Shared/Helpers/db.php
 * @return object[]
 * @throws ReflectionException
 * @throws UnresolvableQueryHandlerException
 */
function tinymce_link_list(): array
{
    $contentResults = ask(new FindContentQuery());
    $productsResults = ask(new FindProductsQuery());

    $results = array_merge($contentResults, $productsResults);

    if (empty($results)) {
        return [];
    }

    return $results;
}

/**
 * Checks if a slug exists among records from the content type table.
 *
 * @file core/Shared/Helpers/db.php
 * @param string $contentTypeId Content Type id to check against.
 * @param string $slug Slug to search for.
 * @return bool Returns true if content type slug exists or false otherwise.
 */
function if_content_type_slug_exists(string $contentTypeId, string $slug): bool
{
    $dfdb = dfdb();

    try {
        $exist = $dfdb->getVar(
            $dfdb->prepare(
                "SELECT COUNT(*) FROM {$dfdb->prefix}content_type WHERE content_type_slug = ? AND content_type_id <> ?",
                [
                    $slug,
                    $contentTypeId
                ]
            )
        );

        return $exist > 0;
    } catch (PDOException $e) {
        logger(
            'error',
            sprintf(
                'SQLSTATE[%s]: %s',
                $e->getCode(),
                $e->getMessage()
            ),
            [
                'Db Function' => 'if_content_type_slug_exists'
            ]
        );
    }

    return false;
}

/**
 * Checks if a slug exists among records from the content table.
 *
 * @file core/Shared/Helpers/db.php
 * @param string $contentId Content id to check against or null.
 * @param string $slug Slug to search for.
 * @param string $contentType The content type to filter.
 * @return bool Returns true if content slug exists or false otherwise.
 */
function if_content_slug_exists(string $contentId, string $slug, string $contentType): bool
{
    $dfdb = dfdb();

    try {
        $exist = $dfdb->getVar(
            $dfdb->prepare(
                "SELECT COUNT(*) FROM {$dfdb->prefix}content 
                WHERE content_slug = ? AND content_id <> ? AND content_type = ?",
                [
                    $slug,
                    $contentId,
                    $contentType
                ]
            )
        );

        return $exist > 0;
    } catch (PDOException $e) {
        logger(
            'error',
            sprintf(
                'SQLSTATE[%s]: %s',
                $e->getCode(),
                $e->getMessage()
            ),
            [
                'Db Function' => 'if_content_slug_exists'
            ]
        );
    }

    return false;
}

/**
 * Checks if a site slug exists.
 *
 * @file core/Shared/Helpers/db.php
 * @param string $siteId Site id to check against.
 * @param string $slug Slug to search for.
 * @return bool Returns true if site slug exists or false otherwise.
 */
function if_site_slug_exists(string $siteId, string $slug): bool
{
    $dfdb = dfdb();

    try {
        $exist = $dfdb->getVar(
            $dfdb->prepare(
                "SELECT COUNT(*) FROM {$dfdb->basePrefix}site WHERE site_slug = ? AND site_id <> ?",
                [
                    $slug,
                    $siteId
                ]
            )
        );

        return $exist > 0;
    } catch (PDOException $e) {
        logger(
            'error',
            sprintf(
                'SQLSTATE[%s]: %s',
                $e->getCode(),
                $e->getMessage()
            ),
            [
                'Db Function' => 'if_site_slug_exists'
            ]
        );
    }

    return false;
}

/**
 * Checks if a product slug exists.
 *
 * @file core/Shared/Helpers/db.php
 * @param string $productId Product id to check against.
 * @param string $slug Slug to search for.
 * @return bool Returns true if site slug exists or false otherwise.
 */
function if_product_slug_exists(string $productId, string $slug): bool
{
    $dfdb = dfdb();

    try {
        $exist = $dfdb->getVar(
            $dfdb->prepare(
                "SELECT COUNT(*) FROM {$dfdb->prefix}product WHERE product_slug = ? AND product_id <> ?",
                [
                    $slug,
                    $productId
                ]
            )
        );

        return $exist > 0;
    } catch (PDOException $e) {
        logger(
            'error',
            sprintf(
                'SQLSTATE[%s]: %s',
                $e->getCode(),
                $e->getMessage()
            ),
            [
                'Db Function' => 'if_product_slug_exists'
            ]
        );
    }

    return false;
}

/**
 * Checks if content has any children.
 *
 * @file core/Shared/Helpers/db.php
 * @param string $contentId Content id to check.
 * @return bool|array| False if content has no children or array of children if true.
 */
function is_content_parent(string $contentId): bool|array
{
    $dfdb = dfdb();

    try {
        $children = $dfdb->getResults(
            $dfdb->prepare(
                "SELECT * FROM {$dfdb->prefix}content WHERE content_parent = ?",
                [
                    $contentId
                ]
            ),
            Database::ARRAY_A
        );

        if (count($children) <= 0) {
            return false;
        }

        return $children;
    } catch (PDOException $e) {
        logger(
            'error',
            sprintf(
                'SQLSTATE[%s]: %s',
                $e->getCode(),
                $e->getMessage()
            ),
            [
                'Db Function' => 'is_content_parent'
            ]
        );
    }

    return false;
}

/**
 * Checks if a given content type exists on content.
 *
 * @file core/Shared/Helpers/db.php
 * @param string $contentType Content Type slug to check for.
 * @return bool Returns true if content type exists or false otherwise.
 */
function if_content_type_exists(string $contentType): bool
{
    $dfdb = dfdb();

    try {
        $exist = $dfdb->getVar(
            $dfdb->prepare(
                "SELECT COUNT(*) FROM {$dfdb->prefix}content WHERE content_type = ?",
                [
                    $contentType
                ]
            )
        );

        return $exist > 0;
    } catch (PDOException $e) {
        logger(
            'error',
            sprintf(
                'SQLSTATE[%s]: %s',
                $e->getCode(),
                $e->getMessage()
            ),
            [
                'Db Function' => 'if_contents_content_type_exists'
            ]
        );
    }

    return false;
}

/**
 * Reassigns content to a different user.
 *
 * @file core/Shared/Helpers/db.php
 * @param string $userId ID of user being removed.
 * @param string $assignId ID of user to whom content will be assigned.
 * @return bool
 * @throws Exception
 */
function reassign_content(string $userId, string $assignId): bool
{
    $dfdb = dfdb();

    try {
        $count = $dfdb->getVar(
            $dfdb->prepare(
                "SELECT COUNT(*) FROM {$dfdb->prefix}content WHERE content_author = ?",
                [
                    $userId
                ]
            )
        );
    } catch (PDOException $e) {
        logger(
            'error',
            sprintf(
                'SQLSTATE[%s]: %s',
                $e->getCode(),
                $e->getMessage()
            ),
            [
                'Db Function' => 'reassign_content'
            ]
        );
    }

    if ($count > 0) {
        try {

            $dfdb->transactional(function () use ($dfdb, $userId, $assignId) {
                $dfdb->table(tableName: $dfdb->prefix . 'content')
                    ->where(condition: 'content_author', parameters: $userId)
                    ->update(data: ['content_author' => $assignId]);
            });

            return true;
        } catch (PDOException | \Exception $e) {
            Devflow::$PHP->flash->error(
                sprintf(
                    trans_html(
                        string: 'Reassign content error: %s',
                    ),
                    $e->getMessage()
                )
            );
        }
    }

    return false;
}

/**
 * Reassigns sites to a different user.
 *
 * @file core/Shared/Helpers/db.php
 * @param string $userId ID of user being removed.
 * @param array $params User parameters (assign_id and role).
 * @return bool
 * @throws CommandPropertyNotFoundException
 * @throws Exception
 * @throws ReflectionException
 * @throws SessionException
 * @throws TypeException
 * @throws UnresolvableCommandHandlerException
 */
function reassign_sites(string $userId, array $params = []): bool
{
    $dfdb = dfdb();

    if ('' === $userId) {
        return false;
    }

    try {
        $count = $dfdb->getVar(
            $dfdb->prepare(
                "SELECT COUNT(*) FROM {$dfdb->basePrefix}site WHERE site_owner = ?",
                [
                    $userId
                ]
            )
        );

        if ($count > 0) {
            $command = new UpdateSiteOwnerCommand([
                'id' => SiteId::fromString($params['site_id']),
                'owner' => UserId::fromString($params['assign_id']),
                'modified' => QubusDateTimeImmutable::now(get_option(key: 'site_timezone')),
            ]);

            command($command);
        }

        return true;
    } catch (PDOException $e) {
        logger(
            'error',
            sprintf(
                'SQLSTATE[%s]: %s',
                $e->getCode(),
                $e->getMessage()
            ),
            [
                'Db Function' => 'reassign_sites'
            ]
        );
    }

    return false;
}

/**
 * Checks if the requested user is an admin of any sites or has any admin roles.
 *
 * @file core/Shared/Helpers/db.php
 * @param string $userId ID of user to check.
 * @return bool Returns true if user has sites and false otherwise.
 * @throws CommandPropertyNotFoundException
 * @throws Exception
 * @throws ReflectionException
 * @throws TypeException
 * @throws UnresolvableQueryHandlerException
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 */
function if_user_has_sites(string $userId): bool
{
    if ('' === $userId) {
        return false;
    }

    $query = new FindSitesByOwnerQuery([
        'owner' => UserId::fromString($userId),
    ]);

    $results = ask($query);

    if (!empty($results)) {
        return true;
    }

    $option = get_user_option('role', $userId);
    if ($option === 'super' || $option === 'admin') {
        return true;
    }
    return false;
}

/**
 * Get an array of sites by owner.
 *
 * @file core/Shared/Helpers/db.php
 * @param string $userId The owner's id.
 * @return array|object
 * @throws CommandPropertyNotFoundException
 * @throws ReflectionException
 * @throws TypeException
 * @throws UnresolvableQueryHandlerException
 */
function get_owner_sites(string $userId): array|object
{
    if ('' === $userId) {
        return [];
    }

    $query = new FindSitesByOwnerQuery([
        'owner' => UserId::fromString($userId),
    ]);

    return ask($query);
}

/**
 * Populate the option cache.
 *
 * @access private
 * @file core/Shared/Helpers/db.php
 * @return bool
 * @throws ContainerExceptionInterface
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 */
function populate_options_cache(): bool
{
    $dfdb = dfdb();

    try {
        $options = $dfdb->getResults(query: "SELECT * FROM {$dfdb->prefix}option", output: Database::ARRAY_A);
        foreach ($options as $option) {
            SimpleCacheObjectCacheFactory::make(
                namespace: $dfdb->prefix . 'options'
            )->set(md5($option['option_key']), $option['option_value']);
        }
        return true;
    } catch (PDOException $e) {
        logger(
            'error',
            sprintf(
                'SQLSTATE[%s]: %s',
                $e->getCode(),
                $e->getMessage()
            ),
            [
                'Db Function' => 'populate_options_cache'
            ]
        );
    }

    return false;
}

/**
 * Login Details Email
 *
 * Function used to send login details to new
 * user.
 *
 * @file core/Shared/Helpers/db.php
 * @throws ContainerExceptionInterface
 * @throws EnvironmentIsBrokenException
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws SessionException
 */
function cms_nodeq_login_details(): void
{
    $table = 'login';
    $nodeq = Node::open(storage_path("app/queue/{$table}"));

    $sql = $nodeq->where('sent', (int) 0)->get();

    if (count(array_filter($sql)) === 0) {
        foreach ($sql as $r) {
            $nodeq->where('_id', esc_html($r['_id']))->delete();
        }
    }

    if (count($sql) > 0) {
        foreach ($sql as $r) {
            $siteName = get_option(key: 'sitename');
            /** @var User $user */
            $user = get_userdata($r['userid']);
            try {
                $password = Crypto::decrypt(
                    $r['pass'],
                    Key::loadFromAsciiSafeString(config()->string(key: 'auth.encryption_key'))
                );

                $message = trans_html('Hi there,') . "<br />";
                $message .= "<p>" . sprintf(
                    trans_html(
                        string: "Welcome to %s! Here's how to log in: ",
                    ),
                    $siteName
                );
                $message .= $r['login_url'] . "</p>";
                $message .= sprintf(trans_html('Username: %s'), $user->login) . "<br />";
                $message .= sprintf(trans_html('Password: %s'), $password) . "<br />";
                $message .= "<p>" . sprintf(
                    trans(
                        string: 'If you have any problems, please contact us at <a href="mailto:%s">%s</a>.',
                    ),
                    get_option(key: 'admin_email'),
                    get_option(key: 'admin_email')
                ) . "</p>";

                $message = process_email_html($message, trans_html('New Account'));
                $headers[] = sprintf("From: %s <auto-reply@%s>", $siteName, $r['domain_name']);
                $headers[] = 'Content-Type: text/html; charset="UTF-8"';
                $headers[] = sprintf("X-Mailer: Devflow %s", Devflow::release());
                try {
                    mail(
                        $user->email,
                        sprintf(
                            trans_html('[%s] New Account'),
                            $siteName
                        ),
                        $message,
                        $headers
                    );
                } catch (\PHPMailer\PHPMailer\Exception $e) {
                    Devflow::$PHP->flash->error($e->getMessage());
                }

                $upd = $nodeq->where('_id', esc_html($r['_id']));
                $upd->update([
                    'sent' => 1
                ]);
            } catch (BadFormatException $e) {
                logger(
                    'error',
                    sprintf(
                        'CRYPTOFORMAT[%s]: %s',
                        $e->getCode(),
                        $e->getMessage()
                    )
                );
            } catch (WrongKeyOrModifiedCiphertextException $e) {
                logger(
                    'error',
                    sprintf(
                        'CRYPTOKEY[%s]: %s',
                        $e->getCode(),
                        $e->getMessage()
                    )
                );
            }
        }
    }
}

/**
 * Reset Password Email
 *
 * Function used to send reset password to a user.
 *
 * @file core/Shared/Helpers/db.php
 * @throws ContainerExceptionInterface
 * @throws EnvironmentIsBrokenException
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function cms_nodeq_reset_password(): void
{
    $table = 'password_reset';
    $nodeq = Node::open(storage_path("app/queue/{$table}"));

    $sql = $nodeq->where('sent', (int) 0)->get();

    if (count($sql) === 0) {
        foreach ($sql as $r) {
            $nodeq->where('_id', esc_html($r['_id']))
                ->delete();
        }
    }

    if (count($sql) > 0) {
        foreach ($sql as $r) {
            $siteName = get_option(key: 'sitename');
            /** @var User $user */
            $user = get_userdata($r['userid']);
            try {
                $password = Crypto::decrypt(
                    $r['pass'],
                    Key::loadFromAsciiSafeString(config('auth.encryption_key'))
                );

                $message = trans_html('Hi there,') . "<br />";
                $message .= "<p>" . sprintf(
                    trans_html(
                        string: "Your password has been reset for %s: ",
                    ),
                    $siteName
                );
                $message .= $r['login_url'] . "</p>";
                $message .= sprintf(trans_html('Username: %s'), $user->login) . "<br />";
                $message .= sprintf(trans_html('Password: %s'), $password) . "<br />";
                $message .= "<p>" . sprintf(
                    trans(
                        string: 'If you have any problems, please contact us at <a href="mailto:%s">%s</a>.',
                    ),
                    get_option(key: 'admin_email'),
                    get_option(key: 'admin_email')
                ) . "</p>";

                $message = process_email_html($message, trans_html('Password Reset'));
                $headers[] = sprintf("From: %s <auto-reply@%s>", $siteName, $r['domain_name']);
                $headers[] = 'Content-Type: text/html; charset="UTF-8"';
                $headers[] = sprintf("X-Mailer: Devflow %s", Devflow::release());
                try {
                    mail(
                        $user->email,
                        sprintf(
                            trans_html('[%s] Password Reset'),
                            $siteName
                        ),
                        $message,
                        $headers
                    );
                } catch (\PHPMailer\PHPMailer\Exception $e) {
                    Devflow::$PHP->flash->error($e->getMessage());
                }

                $upd = $nodeq->where(
                    '_id',
                    esc_html($r['_id'])
                );
                $upd->update([
                    'sent' => 1
                ]);
            } catch (BadFormatException $e) {
                logger(
                    'error',
                    sprintf(
                        'CRYPTOFORMAT[%s]: %s',
                        $e->getCode(),
                        $e->getMessage()
                    )
                );
            } catch (WrongKeyOrModifiedCiphertextException $e) {
                logger(
                    'error',
                    sprintf(
                        'CRYPTOKEY[%s]: %s',
                        $e->getCode(),
                        $e->getMessage()
                    )
                );
            } catch (Exception $e) {
                logger(
                    'error',
                    sprintf(
                        'CRYPTO[%s]: %s',
                        $e->getCode(),
                        $e->getMessage()
                    )
                );
            }
        }
    }
}

/**
 * Creates a new collection.
 *
 * @file core/Shared/Helpers/db.php
 * @param string|null $value
 * @return Collection
 * @throws CommandPropertyNotFoundException
 * @throws ReflectionException
 * @throws UnresolvableQueryHandlerException
 */
function collection(?string $value = null): Collection
{
    $array = match ($value) {
        'user','users' => get_all_users(),
        'site','sites' => get_all_sites(),
        'content' => get_all_content(),
        'contentType','type','contenttype','contentype' => get_all_content_types(),
        'product','products' => get_all_products_with_filters(),
        default => [],
    };

    return new ArrayCollection($array);
}

/**
 * A private function for generating unique site key.
 *
 * @access private
 * @param int $length
 * @return string
 */
function generate_site_key(int $length = 6): string
{
    $key = strtolower(generate_unique_key($length));
    return dfdb()->basePrefix . "{$key}_";
}

/**
 * @file core/Shared/Helpers/db.php
 * @param string $siteId
 * @param string $userId
 * @return bool
 */
function has_site_user_record(string $siteId, string $userId): bool
{
    $dfdb = dfdb();

    try {
        $exist = $dfdb->getVar(
            $dfdb->prepare(
                "SELECT COUNT(*) FROM {$dfdb->basePrefix}site_user WHERE site_id = ? AND user_id = ?",
                [
                    $siteId,
                    $userId
                ]
            )
        );

        return $exist > 0;
    } catch (PDOException $e) {
        logger(
            level: 'error',
            message: sprintf(
                'SQLSTATE[%s]: %s',
                $e->getCode(),
                $e->getMessage()
            ),
            context: [
                'Db Function' => 'has_site_user_record'
            ]
        );
    }

    return false;
}

/**
 * @file core/Shared/Helpers/db.php
 * @throws ReflectionException
 * @throws UnresolvableQueryHandlerException
 */
function unassigned_sites(string $userId): void
{
    foreach(get_all_sites() as $site) {
        if(!has_site_user_record($site['id'], $userId)) {
            echo '<option value="'.$site['id'].'">'.$site['name'].'</option>' . "\n";
        }
    }
}

/**
 * Retrieve a list of super admins from site_user.
 *
 * @return array
 * @throws Exception
 */
function get_super_admins(): array
{
    $dfdb = dfdb();
    $results = $dfdb->getResults(
        query: "SELECT user_id, user_attribute"
        . " FROM {$dfdb->basePrefix}site_user",
        output: Database::ARRAY_A
    );

    if(is_false__($results)) {
        return [];
    }

    $supers = [];
    foreach($results as $row) {
        $json = json_decode($row['user_attribute'], true);
        if($json['role'] === 'super') {
            $supers[] = esc_html($row['user_id']);
        }
    }

    return $supers;
}

/**
 * Checks whether a user is a super admin.
 *
 * @param string|null $userId
 * @return bool
 * @throws ReflectionException
 * @throws Exception
 */
function is_super_admin(?string $userId = null): bool
{

    if(is_null($userId)) {
        $userId = get_current_user_id();
    }

    if(in_array($userId, get_super_admins())) {
        return true;
    }

    return false;
}
