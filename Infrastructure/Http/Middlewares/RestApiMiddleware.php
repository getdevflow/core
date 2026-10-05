<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middlewares;

use App\Infrastructure\Services\Options;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Exception;
use Qubus\Http\Factories\JsonResponseFactory;
use ReflectionException;

use function hash_equals;
use function is_string;

class RestApiMiddleware implements MiddlewareInterface
{
    public function __construct(protected Options $option)
    {
    }

    /**
     * @inheritDoc
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ReflectionException
     * @throws \Exception
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $key = $this->option->read(optionKey: 'api_key');
        $authorization = $request->getHeaderLine('Authorization');

        // Keys in URLs leak through access logs, browser history, and Referer headers.
        // An empty configured key must never authenticate an empty Bearer header.
        if (
            is_string($key) && $key !== '' &&
            preg_match('/^Bearer ([^\\s]+)$/iD', $authorization, $matches) === 1 &&
            hash_equals($key, $matches[1])
        ) {
            return $handler->handle($request);
        }

        return JsonResponseFactory::create(
            data: 'Unauthorized.',
            status: 401
        )->withHeader('WWW-Authenticate', 'Bearer')->withHeader('Cache-Control', 'no-store');
    }
}
