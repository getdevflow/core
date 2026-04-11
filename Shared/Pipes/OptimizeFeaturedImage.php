<?php

declare(strict_types=1);

namespace App\Shared\Pipes;

use Closure;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qubus\Exception\Exception;
use ReflectionException;

use function App\Shared\Helpers\cms_optimized_image_upload;

class OptimizeFeaturedImage
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
        $formattedImage = empty($body['featuredImage'])
        ? '' : cms_optimized_image_upload($body['featuredImage']);

        $featuredImage = array_merge($body, [
            'featuredImage' => $formattedImage,
        ]);
        $request = $request->withParsedBody($featuredImage);

        return $next($request);
    }
}
