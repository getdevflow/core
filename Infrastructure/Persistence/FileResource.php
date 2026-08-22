<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use Codefy\Framework\Auth\Rbac\Entity\Permission;
use Codefy\Framework\Auth\Rbac\Entity\Role;
use Codefy\Framework\Auth\Rbac\Exception\SentinelException;
use Codefy\Framework\Auth\Rbac\Resource\BaseStorageResource;
use Codefy\Framework\Support\LocalStorage;
use JsonException;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Qubus\Exception\Data\TypeException;
use RuntimeException;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

final class FileResource extends BaseStorageResource
{
    /**
     * @var string
     */
    protected string $file;

    private FilesystemOperator $filesystem;

    /**
     * @param string $file
     * @param FilesystemOperator|null $filesystem
     * @throws TypeException
     */
    public function __construct(string $file, ?FilesystemOperator $filesystem = null)
    {
        $this->file = $file;
        $this->filesystem = $filesystem ?? LocalStorage::disk();
    }

    /**
     * @throws FilesystemException
     * @throws SentinelException
     * @throws JsonException
     */
    public function load(): void
    {
        $this->clear();

        if (! $this->filesystem->fileExists($this->file)) {
            return;
        }

        $decoded = json_decode($this->filesystem->read($this->file), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException('RBAC storage must contain a JSON object.');
        }

        $data = $decoded;

        $this->restorePermissions($data['permissions'] ?? []);
        $this->restoreRoles($data['roles'] ?? []);
    }

    /**
     * @throws FilesystemException
     * @throws JsonException
     */
    public function save(): void
    {
        $data = [
            'roles' => [],
            'permissions' => [],
        ];
        foreach ($this->roles as $role) {
            $data['roles'][$role->name] = $this->roleToRow($role);
        }
        foreach ($this->permissions as $permission) {
            $data['permissions'][$permission->name] = $this->permissionToRow($permission);
        }

        $json = json_encode(value: $data, flags: JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        $this->filesystem->write($this->file, $json);
    }

    protected function roleToRow(Role $role): array
    {
        $result = [];
        $result['name'] = $role->name;
        $result['description'] = $role->description;
        $childrenNames = [];
        foreach ($role->getChildren() as $child) {
            $childrenNames[] = $child->name;
        }
        $result['children'] = $childrenNames;
        $permissionNames = [];
        foreach ($role->getPermissions() as $permission) {
            $permissionNames[] = $permission->name;
        }
        $result['permissions'] = $permissionNames;
        return $result;
    }

    protected function permissionToRow(Permission $permission): array
    {
        $result = [];
        $result['name'] = $permission->name;
        $result['description'] = $permission->description;
        $childrenNames = [];
        foreach ($permission->getChildren() as $child) {
            $childrenNames[] = $child->name;
        }
        $result['children'] = $childrenNames;
        $result['ruleClass'] = $permission->getRuleClass();
        return $result;
    }

    /**
     * @throws SentinelException
     */
    protected function restorePermissions(array $permissionsData): void
    {
        /** @var string[][] $permChildrenNames */
        $permChildrenNames = [];

        foreach ($permissionsData as $pData) {
            $permission = $this->addPermission($pData['name'] ?? '', $pData['description'] ?? '');
            $permission->setRuleClass($pData['ruleClass'] ?? '');
            $permChildrenNames[$permission->name] = $pData['children'] ?? [];
        }

        foreach ($permChildrenNames as $permissionName => $childrenNames) {
            foreach ($childrenNames as $childName) {
                $permission = $this->getPermission($permissionName);
                $child = $this->getPermission($childName);
                if ($permission && $child) {
                    $permission->addChild($child);
                }
            }
        }
    }

    /**
     * @throws SentinelException
     */
    protected function restoreRoles($rolesData): void
    {
        /** @var string[][] $rolesChildrenNames */
        $rolesChildrenNames = [];

        foreach ($rolesData as $rData) {
            $role = $this->addRole($rData['name'] ?? '', $rData['description'] ?? '');
            $rolesChildrenNames[$role->name] = $rData['children'] ?? [];
            $permissionNames = $rData['permissions'] ?? [];
            foreach ($permissionNames as $permissionName) {
                if ($permission = $this->getPermission($permissionName)) {
                    $role->addPermission($permission);
                }
            }
        }

        foreach ($rolesChildrenNames as $roleName => $childrenNames) {
            foreach ($childrenNames as $childName) {
                $role = $this->getRole($roleName);
                $child = $this->getRole($childName);
                if ($role && $child) {
                    $role->addChild($child);
                }
            }
        }
    }
}
