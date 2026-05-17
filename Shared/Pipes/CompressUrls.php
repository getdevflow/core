<?php

declare(strict_types=1);

namespace App\Shared\Pipes;

use Closure;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qubus\Exception\Exception;
use ReflectionException;

use function App\Shared\Helpers\cms_compress_internal_urls;

class CompressUrls
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

        $formattedBody = array_merge($body, [
            'body' => cms_compress_internal_urls($body['body']),
        ]);
        $request = $request->withParsedBody($formattedBody);

        return $next($request);
    }
}
