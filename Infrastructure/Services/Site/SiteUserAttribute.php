<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Site;

final readonly class SiteUserAttribute
{
    public function __construct(
        public string $siteId,
        public string $userId,
        public int $adminLayout,
        public int $adminSidebar,
        public string $adminSkin,
        public ?string $role = null,
        public ?string $status = null,
    ) {
    }

    public static function default(string $siteId, string $userId): self
    {
        return new self(
            siteId: $siteId,
            userId: $userId,
            adminLayout: 0,
            adminSidebar: 0,
            adminSkin: 'skin-red',
            role: null,
            status: null,
        );
    }

    public function withRole(?string $role = null): self
    {
        return new self(
            siteId: $this->siteId,
            userId: $this->userId,
            adminLayout: $this->adminLayout,
            adminSidebar: $this->adminSidebar,
            adminSkin: $this->adminSkin,
            role: $role,
            status: $this->status,
        );
    }

    public function withStatus(?string $status = null): self
    {
        return new self(
            siteId: $this->siteId,
            userId: $this->userId,
            adminLayout: $this->adminLayout,
            adminSidebar: $this->adminSidebar,
            adminSkin: $this->adminSkin,
            role: $this->role,
            status: $status,
        );
    }

    public function withAdminLayout(int $adminLayout): self
    {
        return new self(
            siteId: $this->siteId,
            userId: $this->userId,
            adminLayout: $adminLayout,
            adminSidebar: $this->adminSidebar,
            adminSkin: $this->adminSkin,
            role: $this->role,
            status: $this->status,
        );
    }

    public function withAdminSidebar(int $adminSidebar): self
    {
        return new self(
            siteId: $this->siteId,
            userId: $this->userId,
            adminLayout: $this->adminLayout,
            adminSidebar: $adminSidebar,
            adminSkin: $this->adminSkin,
            role: $this->role,
            status: $this->status,
        );
    }

    public function withAdminSkin(string $adminSkin): self
    {
        return new self(
            siteId: $this->siteId,
            userId: $this->userId,
            adminLayout: $this->adminLayout,
            adminSidebar: $this->adminSidebar,
            adminSkin: $adminSkin,
            role: $this->role,
            status: $this->status,
        );
    }

    /**
     * @return array<string, scalar|null>
     */
    public function toArray(): array
    {
        return [
            'site_id' => $this->siteId,
            'user_id' => $this->userId,
            'role' => $this->role,
            'status' => $this->status,
            'admin_layout' => $this->adminLayout,
            'admin_sidebar' => $this->adminSidebar,
            'admin_skin' => $this->adminSkin,
        ];
    }
}
