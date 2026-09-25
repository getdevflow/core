<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middlewares;

use Codefy\Framework\Http\Middleware\Csrf\CsrfProtectionMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\EventDispatcher\ActionFilter\Filter;
use Qubus\Exception\Exception;
use Qubus\Routing\Route\RouteAttributes;
use ReflectionException;

final readonly class CmsCsrfMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CsrfProtectionMiddleware $csrf,
        private RestApiMiddleware $api
    ) {
    }

    /**
     * @throws ReflectionException
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws \Exception
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Resource APIs authenticate explicit credentials, never ambient browser cookies.
        // Validate the credential before bypassing CSRF, including for unsafe methods.
        if (str_starts_with($request->getUri()->getPath(), '/v2/')) {
            return $this->api->process($request, $handler);
        }

        // Plugins may register callbacks that authenticate provider signatures.
        // No callback is exempt unless its owning plugin registers it.
        $signatureRoutes = Filter::getInstance()->applyFilter('cms.csrf.signature_routes', []);
        $routeName = $request->getAttribute(RouteAttributes::NAME);
        if (
            $request->getMethod() === 'POST' && is_string($routeName) &&
            isset($signatureRoutes[$routeName]) &&
            rtrim($request->getUri()->getPath(), '/') === $signatureRoutes[$routeName]
        ) {
            // These named plugin handlers verify the provider signature before any writes.
            return $handler->handle($request);
        }

        return $this->csrf->process($request, $handler);
    }
}
