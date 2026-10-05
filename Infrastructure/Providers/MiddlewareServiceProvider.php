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
        $middlewares = array_replace(
            [
                'current.site' => CurrentSiteMiddleware::class,
                'archived.site' => ArchivedSiteMiddleware::class,
            ],
            $this->codefy->configContainer->array('app.middlewares', [])
        );
        $this->codefy->configContainer->setConfigKey('app', ['middlewares' => $middlewares]);
    }
}
