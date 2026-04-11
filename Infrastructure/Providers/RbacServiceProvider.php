<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Infrastructure\Persistence\FileResource;
use App\Infrastructure\Persistence\Repository\AuthenticationRepository;
use App\Infrastructure\Services\RbacLoader;
use Codefy\Framework\Auth\Auth;
use Codefy\Framework\Auth\Rbac\Rbac;
use Codefy\Framework\Auth\Rbac\Resource\StorageResource;
use Codefy\Framework\Auth\Repository\AuthUserRepository;
use Codefy\Framework\Auth\Sentinel;
use Codefy\Framework\Support\CodefyServiceProvider;
use Qubus\Exception\Exception;

final class RbacServiceProvider extends CodefyServiceProvider
{
    /**
     * @throws Exception
     */
    public function register(): void
    {
        $this->codefy->alias(original: StorageResource::class, alias: FileResource::class);
        $this->codefy->define(name: FileResource::class, args: [
            ':file' => 'rbac.json'
        ]);
        $this->codefy->share(nameOrInstance: StorageResource::class);

        $this->codefy->share(nameOrInstance: Rbac::class);

        /** @var RbacLoader $loader */
        $loader = $this->codefy->make(name: RbacLoader::class);
        $loader->initRbacPermissions();
        $loader->initRbacRoles();

        $this->codefy->alias(original: Sentinel::class, alias: Auth::class);
        $this->codefy->share(nameOrInstance: Sentinel::class);
        $this->codefy->alias(original: AuthUserRepository::class, alias: AuthenticationRepository::class);
        $this->codefy->share(nameOrInstance: AuthUserRepository::class);
    }
}
