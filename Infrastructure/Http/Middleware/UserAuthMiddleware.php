<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Infrastructure\Services\UserAuth;
use Codefy\Framework\Proxy\Codefy;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Qubus\Config\ConfigContainer;
use Qubus\Exception\Data\TypeException;
use Qubus\Http\Factories\JsonResponseFactory;
use Qubus\Http\Factories\RedirectResponseFactory;

class UserAuthMiddleware implements MiddlewareInterface
{
    private ?string $permission = null;
    private ?string $redirect = null;
    private bool|string $redirectIfAuthorized = false;

    public function __construct(private readonly UserAuth $user, private readonly ConfigContainer $configContainer)
    {
    }

    public function withArguments(
        ?string $permission = null,
        ?string $redirect = null,
        bool|string $redirectIfAuthorized = false
    ): self {
        $clone = clone $this;
        $clone->permission = $permission;
        $clone->redirect = $redirect;
        $clone->redirectIfAuthorized = $redirectIfAuthorized;

        return $clone;
    }

    /**
     * @inheritDoc
     * @throws TypeException
     * @throws \Exception
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (null === $this->permission) {
            return JsonResponseFactory::create('Gate is not set properly.');
        }

        if (false === $this->user->can(permissionName: $this->permission)) {
            Codefy::$PHP->flash->error(
                message: $this->configContainer->getConfigKey(
                    key: 'auth.access_denied_message',
                    default: 'Access denied.'
                ),
            );

            return $this->redirect !== null
            ? RedirectResponseFactory::create(uri: $this->redirect)
            : JsonResponseFactory::create(data: 'Access denied.');
        }

        if ((bool) $this->redirectIfAuthorized && $this->redirect !== null) {
            return RedirectResponseFactory::create(uri: $this->redirect);
        }

        return $handler->handle($request);
    }
}
