<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Site;

use Psr\SimpleCache\CacheInterface;
use Throwable;

use function md5;

final readonly class SiteUserAttributeManager
{
    public function __construct(
        private SiteUserAttributeRepository $repository,
        private CacheInterface $cache,
    ) {
    }

    public function get(string $siteId, string $userId): SiteUserAttribute
    {
        return $this->load($siteId, $userId);
    }

    public function exists(string $siteId, string $userId): bool
    {
        try {
            $cached = $this->cache->get(md5($siteId.$userId));

            if (is_array($cached)) {
                return true;
            }
        } catch (Throwable) {
        }

        return $this->repository->exists($siteId, $userId);
    }

    public function createIfMissing(string $siteId, string $userId): SiteUserAttribute
    {
        $existing = $this->repository->find($siteId, $userId);

        if ($existing instanceof SiteUserAttribute) {
            $this->warmWith($existing);

            return $existing;
        }

        $attributes = SiteUserAttribute::default($siteId, $userId);

        $this->repository->create($attributes);
        $this->warmWith($attributes);

        return $attributes;
    }

    public function changeAdminLayout(string $siteId, string $userId, int $adminLayout): SiteUserAttribute
    {
        $updated = $this->load($siteId, $userId)->withAdminLayout($adminLayout);

        $this->repository->save($updated);
        $this->warmWith($updated);

        return $updated;
    }

    public function changeAdminSidebar(string $siteId, string $userId, int $adminSidebar): SiteUserAttribute
    {
        $updated = $this->load($siteId, $userId)->withAdminSidebar($adminSidebar);

        $this->repository->save($updated);
        $this->warmWith($updated);

        return $updated;
    }

    public function changeAdminSkin(string $siteId, string $userId, string $adminSkin): SiteUserAttribute
    {
        $updated = $this->load($siteId, $userId)->withAdminSkin($adminSkin);

        $this->repository->save($updated);
        $this->warmWith($updated);

        return $updated;
    }

    public function changeRole(string $siteId, string $userId, string $role): SiteUserAttribute
    {
        $updated = $this->load($siteId, $userId)->withRole($role);

        $this->repository->save($updated);
        $this->warmWith($updated);

        return $updated;
    }

    public function changeStatus(string $siteId, string $userId, string $status): SiteUserAttribute
    {
        $updated = $this->load($siteId, $userId)->withStatus($status);

        $this->repository->save($updated);
        $this->warmWith($updated);

        return $updated;
    }

    public function updateAdminPreferences(
        string $siteId,
        string $userId,
        ?int $layout = null,
        ?int $sidebar = null,
        ?string $skin = null,
    ): SiteUserAttribute {
        $current = $this->load($siteId, $userId);

        $updated = new SiteUserAttribute(
            siteId: $current->siteId,
            userId: $current->userId,
            adminLayout: $layout ?? $current->adminLayout,
            adminSidebar: $sidebar ?? $current->adminSidebar,
            adminSkin: $skin ?? $current->adminSkin,
            role: $current->role,
            status: $current->status,
        );

        $this->repository->save($updated);
        $this->warmWith($updated);

        return $updated;
    }

    public function delete(string $siteId, string $userId): void
    {
        $this->repository->delete($siteId, $userId);
        $this->forget($siteId, $userId);
    }

    public function warm(string $siteId, string $userId): SiteUserAttribute
    {
        $attributes = $this->repository->get($siteId, $userId);
        $this->warmWith($attributes);

        return $attributes;
    }

    public function forget(string $siteId, string $userId): void
    {
        try {
            $this->cache->delete(md5($siteId.$userId));
        } catch (Throwable) {
        }
    }

    private function load(string $siteId, string $userId): SiteUserAttribute
    {
        try {
            $cached = $this->cache->get(md5($siteId.$userId));

            if (is_array($cached)) {
                return $this->fromCache($cached);
            }
        } catch (Throwable) {
        }

        $attributes = $this->repository->get($siteId, $userId);
        $this->warmWith($attributes);

        return $attributes;
    }

    private function warmWith(SiteUserAttribute $attributes): void
    {
        try {
            $this->cache->set(
                md5($attributes->siteId.$attributes->userId),
                $attributes->toArray(),
            );
        } catch (Throwable) {
        }
    }

    /**
     * @param array<string, mixed> $cached
     */
    private function fromCache(array $cached): SiteUserAttribute
    {
        return new SiteUserAttribute(
            siteId: (string) $cached['site_id'],
            userId: (string) $cached['user_id'],
            adminLayout: (int) $cached['admin_layout'],
            adminSidebar: (int) $cached['admin_sidebar'],
            adminSkin: (string) $cached['admin_skin'],
            role: $cached['role'] !== null ? (string) $cached['role'] : null,
            status: $cached['status'] !== null ? (string) $cached['status'] : null,
        );
    }
}
