<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use Qubus\Expressive\Database;
use App\Infrastructure\Services\Options;
use App\Shared\Services\SimpleCacheObjectCacheFactory;
use Codefy\Framework\Support\CodefyServiceProvider;

class OptionsServiceProvider extends CodefyServiceProvider
{
    public function register(): void
    {
        $this->codefy->singleton(Options::class, function () {
            $database = $this->codefy->make(name: Database::class);
            return new Options(
                $database,
                SimpleCacheObjectCacheFactory::make(namespace: $database->prefix . 'options')
            );
        });
    }
}
