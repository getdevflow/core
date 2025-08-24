<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Infrastructure\Persistence\Database;
use App\Infrastructure\Persistence\NativePdoDatabase;
use Codefy\Framework\Support\CodefyServiceProvider;
use PDO;
use Qubus\Config\ConfigContainer;

class DatabaseServiceProvider extends CodefyServiceProvider
{
    public function register(): void
    {
        $this->codefy->singleton(key: Database::class, value: function () {
            return NativePdoDatabase::getInstance(
                pdo: $this->codefy->make(name: PDO::class),
                configContainer: $this->codefy->make(name: ConfigContainer::class)
            );
        });
        $this->codefy->share(nameOrInstance: Database::class);
    }
}
