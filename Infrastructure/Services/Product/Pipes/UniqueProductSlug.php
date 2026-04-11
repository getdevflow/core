<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Product\Pipes;

use Closure;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qubus\Exception\Exception;
use ReflectionException;

use function App\Shared\Helpers\cms_unique_product_slug;

class UniqueProductSlug
{
    /**
     * @param ServerRequestInterface $request
     * @param Closure $next
     * @return mixed
     * @throws Exception
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function handle(ServerRequestInterface $request, Closure $next): mixed
    {
        $body = $request->getParsedBody();

        $slug = array_merge($body, [
            'slug' => cms_unique_product_slug(
                $body['slug'],
                $body['title'],
                $body['id'],
            )
        ]);
        $request = $request->withParsedBody($slug);

        return $next($request);
    }
}
