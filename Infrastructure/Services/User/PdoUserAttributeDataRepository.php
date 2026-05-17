<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\User;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Qubus\Exception\Exception;
use Qubus\Expressive\Database;
use ReflectionException;
use RuntimeException;

use function Codefy\Framework\Helpers\trans;
use function Codefy\Framework\Helpers\trans_html;
use function is_string;
use function sprintf;

final readonly class PdoUserAttributeDataRepository implements UserAttributeRepository
{
    public function __construct(private Database $dfdb)
    {
    }

    /**
     * @param string $siteId
     * @param string $userId
     * @return UserAttributeBag|null
     * @throws Exception
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function find(string $siteId, string $userId): ?UserAttributeBag
    {
        $stmt = $this->dfdb->getConnection()->pdo->prepare(
            "SELECT user_attribute 
             FROM {$this->dfdb->basePrefix}site_user 
             WHERE site_id = :site_id 
             AND user_id = :user_id
             LIMIT 1"
        );

        $stmt->execute(['site_id' => $siteId, 'user_id' => $userId]);

        $json = $stmt->fetchColumn();

        if ($json === false) {
            return null;
        }

        $bag = UserAttributeBag::fromJson(
            $siteId,
            $userId,
            is_string($json) ? $json : null
        );

        return $bag->withExpandedUrls();
    }

    /**
     * @param string $siteId
     * @param string $userId
     * @param mixed $key
     * @param mixed|null $default
     * @return mixed
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function get(string $siteId, string $userId, string $key, mixed $default = null): mixed
    {
        $attribute = $this->find($siteId, $userId);

        if ($attribute === null) {
            throw new RuntimeException(
                sprintf(trans_html('No row found for site "%s" and user "%s".'), $siteId, $userId)
            );
        }

        return $attribute->get($key, $default);
    }

    /**
     * @param string $siteId
     * @param string $userId
     * @return bool
     * @throws \Exception
     */
    public function exists(string $siteId, string $userId): bool
    {
        $stmt = $this->dfdb->getConnection()->pdo->prepare(
            "SELECT 1  
             FROM {$this->dfdb->basePrefix}site_user 
             WHERE site_id = :site_id 
             AND user_id = :user_id
             LIMIT 1"
        );

        $stmt->execute(['site_id' => $siteId, 'user_id' => $userId]);
        $json = $stmt->fetchColumn();

        return $json !== false;
    }

    /**
     * @param string $siteId
     * @param string $userId
     * @param callable $callback
     * @return UserAttributeBag
     * @throws \Throwable
     */
    public function patch(string $siteId, string $userId, callable $callback): UserAttributeBag
    {
        $this->dfdb->getConnection()->pdo->beginTransaction();

        try {
            $stmt = $this->dfdb->getConnection()->pdo->prepare(
                "SELECT user_attribute
                 FROM {$this->dfdb->basePrefix}site_user 
                 WHERE site_id = :site_id 
                 AND user_id = :user_id 
                 LIMIT 1"
            );

            $stmt->execute(['site_id' => $siteId, 'user_id' => $userId]);

            $json = $stmt->fetchColumn();

            if ($json === false) {
                throw new \RuntimeException(
                    trans('User attribute not found')
                );
            }

            $current = UserAttributeBag::fromJson($siteId, $userId, is_string($json) ? $json : null);
            $updated = $callback($current);

            if (!$updated instanceof UserAttributeBag) {
                throw new \RuntimeException(
                    trans('Attribute patch callback must return an AttributeBag instance.')
                );
            }

            $update = $this->dfdb->getConnection()->pdo->prepare(
                "UPDATE {$this->dfdb->basePrefix}site_user
                 SET user_attribute = :user_attribute
                 WHERE site_id = :site_id 
                 AND user_id = :user_id"
            );

            $update->execute([
                'site_id' => $siteId,
                'user_id' => $userId,
                'user_attribute' => $updated->withCompressedUrls()->toJson(),
            ]);

            $this->dfdb->getConnection()->pdo->commit();

            return $updated;
        } catch (\Throwable $e) {
            $this->dfdb->getConnection()->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @param UserAttributeBag $attribute
     * @return void
     * @throws \Exception
     */
    public function create(UserAttributeBag $attribute): void
    {
        $this->dfdb->transactional(function () use ($attribute) {
            $stmt = $this->dfdb->getConnection()->pdo->prepare(
                "INSERT INTO {$this->dfdb->basePrefix}site_user (site_id, user_id, user_attribute)
                 VALUES (:site_id, :user_id, :user_attribute)"
            );

            $stmt->execute([
                'site_id' => $attribute->siteId(),
                'user_id' => $attribute->userId(),
                'user_attribute' => $attribute->withCompressedUrls()->toJson(),
            ]);
        });
    }

    /**
     * @param UserAttributeBag $attribute
     * @return void
     * @throws \Exception
     */
    public function save(UserAttributeBag $attribute): void
    {
        $this->dfdb->transactional(function () use ($attribute) {
            $stmt = $this->dfdb->getConnection()->pdo->prepare(
                "UPDATE {$this->dfdb->basePrefix}site_user
                 SET user_attribute = :user_attribute
                 WHERE site_id = :site_id
                 AND user_id = :user_id"
            );

            $stmt->execute([
                'site_id' => $attribute->siteId(),
                'user_id' => $attribute->userId(),
                'user_attribute' => $attribute->withCompressedUrls()->toJson(),
            ]);
        });
    }

    /**
     * @param string $siteId
     * @param string $userId
     * @return void
     * @throws \Exception
     */
    public function delete(string $siteId, string $userId): void
    {
        $this->dfdb->transactional(function () use ($siteId, $userId) {
            $stmt = $this->dfdb->getConnection()->pdo->prepare(
                "DELETE FROM {$this->dfdb->basePrefix}site_user
                 WHERE site_id = :site_id
                 AND user_id = :user_id"
            );

            $stmt->execute([
                'site_id' => $siteId,
                'user_id' => $userId,
            ]);
        });
    }
}
