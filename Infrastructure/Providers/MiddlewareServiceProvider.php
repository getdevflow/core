<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Infrastructure\Http\Middlewares\ArchivedSiteMiddleware;
use App\Infrastructure\Http\Middlewares\CurrentSiteMiddleware;
use Codefy\Framework\Support\CodefyServiceProvider;
use Qubus\Exception\Exception;

class MiddlewareServiceProvider extends CodefyServiceProvider
{
    /**
     * @throws Exception
     */
    public function register(): void
    {
        $middlewares = $this->codefy->configContainer->array(key: 'app.middlewares');
        $middlewares = array_merge(
            $middlewares,
            [
                'current.site' => CurrentSiteMiddleware::class,
                'archived.site' => ArchivedSiteMiddleware::class,
            ]
        );
        foreach ($middlewares as $key => $value) {
            $this->codefy->alias(original: $key, alias: $value);
        }
    }
}
