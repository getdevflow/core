<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use Qubus\Expressive\Database;
use App\Infrastructure\Persistence\NativePdoDatabase;
use Codefy\Framework\Support\CodefyServiceProvider;

class DatabaseServiceProvider extends CodefyServiceProvider
{
    public function register(): void
    {
        $this->codefy->singleton(key: Database::class, value: function () {
            return new NativePdoDatabase(
                connection: $this->codefy->getDbConnection(),
                configContainer: $this->codefy->configContainer
            );
        });
    }
}
