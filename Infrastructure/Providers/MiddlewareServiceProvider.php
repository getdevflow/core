<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

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
        foreach ($middlewares as $key => $value) {
            $this->codefy->alias(original: $key, alias: $value);
        }
    }
}
