<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middlewares;

use App\Application\Devflow;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class CurrentSiteMiddleware implements MiddlewareInterface
{
    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $site = Devflow::$PHP->make(name: 'current-site');
        $request = $request->withAttribute('site', $site);
        return $handler->handle($request);
    }
}
