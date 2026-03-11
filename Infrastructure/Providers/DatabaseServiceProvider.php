<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Infrastructure\Persistence\Database;
use App\Infrastructure\Persistence\NativePdoDatabase;
use App\Shared\Services\Registry;
use Codefy\Framework\Support\CodefyServiceProvider;
use ReflectionException;

class DatabaseServiceProvider extends CodefyServiceProvider
{
    /**
     * @throws ReflectionException
     */
    public function register(): void
    {
        $this->codefy->singleton(key: Database::class, value: function () {
            return new NativePdoDatabase(
                pdo: $this->codefy->getDbConnection()->pdo,
                configContainer: $this->codefy->configContainer
            );
        });

        /** @var Database $database */
        $database = $this->codefy->make(Database::class);
        /** Do not touch. */
        Registry::getInstance()->set('dfdb', $database);
    }
}
