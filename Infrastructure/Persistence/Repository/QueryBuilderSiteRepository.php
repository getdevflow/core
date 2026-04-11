<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Content\Model\Content;
use App\Domain\Product\Model\Product;
use App\Domain\Site\Model\Site;
use App\Domain\Site\Repository\SiteCommandRepository;
use App\Domain\Site\ValueObject\SiteId;
use App\Domain\User\ValueObject\UserId;
use Qubus\Expressive\Database;
use Exception as NativeException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Exception;
use Qubus\Expressive\QueryBuilderException;
use ReflectionException;

use function App\Shared\Helpers\get_content_by;
use function App\Shared\Helpers\get_product_by;
use function App\Shared\Helpers\get_site_by;
use function Qubus\Support\Helpers\is_false__;

class QueryBuilderSiteRepository implements SiteCommandRepository
{
    public function __construct(protected Database $dfdb)
    {
    }

    /**
     * @param Site $site
     * @return void
     * @throws NativeException
     */
    public function save(Site $site): void
    {
        try {
            $this->dfdb->qb()->transactional(callback: function () use ($site) {
                $this->dfdb->qb()
                    ->table(tableName: $this->dfdb->basePrefix . 'site')
                        ->set([
                            'site_id' => $site->id,
                            'site_key' => $site->key,
                            'site_name' => $site->name,
                            'site_slug' => $site->slug,
                            'site_domain' => $site->domain,
                            'site_mapping' => $site->mapping,
                            'site_path' => $site->path,
                            'site_owner' => $site->owner,
                            'site_status' => $site->status,
                            'site_registered' => $site->registered
                        ])
                    ->save();
            });
        } catch (QueryBuilderException $e) {
            throw new NativeException(message: $e->getMessage());
        }
    }

    /**
     * @param Site $site
     * @return void
     * @throws NativeException
     */
    public function update(Site $site): void
    {
        try {
            $this->dfdb->qb()->transactional(callback: function () use ($site) {
                $this->dfdb->qb()
                    ->table(tableName: $this->dfdb->basePrefix . 'site')
                        ->set([
                            'site_name' => $site->name,
                            'site_slug' => $site->slug,
                            'site_domain' => $site->domain,
                            'site_mapping' => $site->mapping,
                            'site_path' => $site->path,
                            'site_owner' => $site->owner,
                            'site_status' => $site->status,
                            'site_modified' => $site->modified
                        ])
                    ->where(condition: 'site_id = ?', parameters: $site->id)
                    ->update();
            });
        } catch (QueryBuilderException $e) {
            throw new NativeException(message: $e->getMessage());
        }
    }

    /**
     * @param SiteId $siteId
     * @param UserId $authorId
     * @param UserId $assignId
     * @return void
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws NativeException
     */
    public function attributeSiteUser(SiteId $siteId, UserId $authorId, UserId $assignId): void
    {
        try {
            /** @var Site $site */
            $site = get_site_by(field: 'id', value: $siteId->toNative());
            /** @var Content $content */
            $content = get_content_by(field: 'author', value: $authorId->toNative());
            /** @var Product $product */
            $product = get_product_by(field: 'author', value: $authorId->toNative());

            if(!is_false__($content)) {
                $this->dfdb->qb()->transactional(callback: function () use ($authorId, $assignId, $site) {
                    $this->dfdb->qb()
                        ->table(tableName: $site->key . 'content')
                        ->set(['content_author' => $assignId->toNative()])
                        ->where(condition: 'content_author = ?', parameters: $authorId->toNative())
                        ->update();
                });
            }

            if(!is_false__($product)) {
                $this->dfdb->qb()->transactional(callback: function () use ($authorId, $assignId, $site) {
                    $this->dfdb->qb()
                        ->table(tableName: $site->key . 'product')
                        ->set(['product_author' => $assignId->toNative()])
                        ->where(condition: 'product_author = ?', parameters: $authorId->toNative())
                        ->update();
                });
            }
        } catch (QueryBuilderException $e) {
            throw new NativeException(message: $e->getMessage());
        }
    }

    /**
     * @param SiteId $siteId
     * @param UserId $userId
     * @return void
     * @throws NativeException
     */
    public function remove(SiteId $siteId, UserId $userId): void
    {
        try {
            $this->dfdb->qb()->transactional(callback: function () use ($siteId, $userId) {
                $this->dfdb->qb()
                    ->table(tableName: $this->dfdb->basePrefix . 'site_user')
                    ->where(condition: 'site_id = ?', parameters: $siteId->toNative())->and()
                    ->where(condition: 'user_id = ?', parameters: $userId->toNative())
                    ->delete();
            });
        } catch (QueryBuilderException $e) {
            throw new NativeException(message: $e->getMessage());
        }
    }

    /**
     * @param SiteId $id
     * @return void
     * @throws NativeException
     */
    public function destroy(SiteId $id): void
    {
        try {
            $this->dfdb->qb()->transactional(callback: function () use ($id) {
                $this->dfdb->qb()
                    ->table(tableName: $this->dfdb->basePrefix . 'site')
                    ->where(condition: 'site_id = ?', parameters: $id->toNative())
                    ->delete();
            });
        } catch (QueryBuilderException $e) {
            throw new NativeException(message: $e->getMessage());
        }
    }

    /**
     * @param Site $site
     * @return void
     * @throws NativeException
     */
    public function updateOwner(Site $site): void
    {
        try {
            $this->dfdb->qb()->transactional(callback: function () use ($site) {
                $this->dfdb->qb()
                    ->table(tableName: $this->dfdb->basePrefix . 'site')
                    ->set([
                        'site_owner' => $site->owner,
                        'site_modified' => $site->modified
                    ])
                    ->where(condition: 'site_id = ?', parameters: $site->id)
                    ->update();
            });
        } catch (QueryBuilderException $e) {
            throw new NativeException(message: $e->getMessage());
        }
    }
}
