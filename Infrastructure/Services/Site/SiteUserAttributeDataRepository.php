<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Site;

use Qubus\Expressive\Database;
use Codefy\Framework\Factory\FileLoggerFactory;
use PDOException;
use Qubus\Exception\Exception;
use ReflectionException;

final class SiteUserAttributeDataRepository implements SiteUserAttributeRepository
{
    public function __construct(
        private Database $dfdb {
            get => $this->dfdb;
            set(Database $value) => $this->dfdb = $value;
        },
        private ?string $table = null {
            get => $this->table ?? $this->dfdb->basePrefix . 'site_user';
            set(null|string $value) => $this->table = $value;
        }
    )
    {
    }

    /**
     * @param string $siteId
     * @param string $userId
     * @return SiteUserAttribute|null
     */
    public function find(string $siteId, string $userId): ?SiteUserAttribute
    {
        $row = $this->dfdb->getRow(
            query: $this->dfdb->prepare(
                query: "SELECT * 
                FROM {$this->table} 
                WHERE site_id = ? AND user_id = ? 
                LIMIT 1",
                params: [$siteId, $userId]
            ),
            output: Database::ARRAY_A
        );

        if (!is_array($row) || $row === []) {
            return null;
        }

        return $this->mapRow($row);
    }

    /**
     * @param string $siteId
     * @param string $userId
     * @return SiteUserAttribute
     */
    public function get(string $siteId, string $userId): SiteUserAttribute
    {
        $attributes = $this->find($siteId, $userId);

        if ($attributes === null) {
            throw new \RuntimeException(
                sprintf('No site-user row exists for site "%s" and user "%s".', $siteId, $userId)
            );
        }

        return $attributes;
    }

    /**
     * @param string $siteId
     * @param string $userId
     * @return bool
     */
    public function exists(string $siteId, string $userId): bool
    {
        return $this->dfdb->getVar(
            $this->dfdb->prepare(
                query: "SELECT 1 FROM {$this->table} WHERE site_id = ? AND user_id = ? LIMIT 1",
                params: [$siteId, $userId]
            )
        ) !== null;
    }

    /**
     * @param SiteUserAttribute $attributes
     * @return void
     * @throws \ReflectionException
     */
    public function create(SiteUserAttribute $attributes): void
    {
        try {
            $this->dfdb->transactional(function () use ($attributes): void {
                $this->dfdb
                    ->table($this->table)
                    ->insert($attributes->toArray());
            });
        } catch (PDOException | \Exception $ex) {
            FileLoggerFactory::getLogger()->error(
                sprintf('SITE_USER_ATTRIBUTES[%s]: %s', $ex->getCode(), $ex->getMessage())
            );

            throw new \RuntimeException('Unable to create site-user attributes row.', 0, $ex);
        }
    }

    /**
     * @param SiteUserAttribute $attributes
     * @return void
     * @throws \ReflectionException
     */
    public function save(SiteUserAttribute $attributes): void
    {
        try {
            $this->dfdb->transactional(function () use ($attributes): void {
                $this->dfdb
                    ->table($this->table)
                    ->where('site_id = ?', $attributes->siteId)->and()
                    ->where('user_id = ?', $attributes->userId)
                    ->update([
                        'role' => $attributes->role,
                        'status' => $attributes->status,
                        'admin_layout' => $attributes->adminLayout,
                        'admin_sidebar' => $attributes->adminSidebar,
                        'admin_skin' => $attributes->adminSkin,
                    ]);
            });
        } catch (PDOException | \Exception $ex) {
            FileLoggerFactory::getLogger()->error(
                sprintf('SITE_USER_ATTRIBUTES[%s]: %s', $ex->getCode(), $ex->getMessage())
            );

            throw new \RuntimeException('Unable to save site-user attributes row.', 0, $ex);
        }
    }

    /**
     * @param string $siteId
     * @param string $userId
     * @return void
     * @throws \ReflectionException
     */
    public function delete(string $siteId, string $userId): void
    {
        try {
            $this->dfdb->transactional(function () use ($siteId, $userId): void {
                $this->dfdb
                    ->table($this->table)
                    ->where('site_id = ?', $siteId)->and()
                    ->where('user_id = ?', $userId)
                    ->delete();
            });
        } catch (PDOException | \Exception $ex) {
            FileLoggerFactory::getLogger()->error(
                sprintf('SITE_USER_ATTRIBUTES[%s]: %s', $ex->getCode(), $ex->getMessage())
            );

            throw new \RuntimeException('Unable to delete site-user attributes row.', 0, $ex);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRow(array $row): SiteUserAttribute
    {
        return new SiteUserAttribute(
            siteId: (string) $row['site_id'],
            userId: (string) $row['user_id'],
            adminLayout: (int) $row['admin_layout'],
            adminSidebar: (int) $row['admin_sidebar'],
            adminSkin: (string) $row['admin_skin'],
            role: $row['role'] !== null ? (string) $row['role'] : null,
            status: $row['status'] !== null ? (string) $row['status'] : null,
        );
    }
}
