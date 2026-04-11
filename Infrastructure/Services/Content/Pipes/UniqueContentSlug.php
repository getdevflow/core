<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Content\Pipes;

use Closure;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;

use function App\Shared\Helpers\cms_unique_content_slug;

class UniqueContentSlug
{
    /**
     * @param ServerRequestInterface $request
     * @param Closure $next
     * @return mixed
     * @throws Exception
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws TypeException
     */
    public function handle(ServerRequestInterface $request, Closure $next): mixed
    {
        $currentBody = $request->getParsedBody();

        $contentSlug = array_merge($currentBody, [
            'slug' => cms_unique_content_slug(
                $currentBody['slug'],
                $currentBody['title'],
                $currentBody['id'],
                $currentBody['type']
            )
        ]);
        $request = $request->withParsedBody($contentSlug);

        return $next($request);
    }
}
