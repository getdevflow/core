<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Vihzhuo;

use App\Application\Devflow;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qubus\Http\Factories\EmptyResponseFactory;
use Qubus\Http\Response;
use Vihzhuo\Contracts\AuthContract;

use function App\Shared\Helpers\current_user_can;
use function App\Shared\Helpers\cms_clear_auth_cookie;
use function App\Shared\Helpers\login_url;
use function App\Shared\Helpers\is_user_logged_in;
use function phpb_redirect;

class VihzhuoAuth implements AuthContract
{
    /**
     * @inheritDoc
     * @param ServerRequestInterface $request
     * @param string|null $action
     * @return ResponseInterface|null
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function handleRequest(ServerRequestInterface $request, ?string $action = null): ?ResponseInterface
    {
        if ($action === 'logout') {
            if ($request->getMethod() !== 'POST') {
                return new Response(status: 405, headers: ['Allow' => 'POST']);
            }
            cms_clear_auth_cookie();
            return phpb_redirect(url: login_url());
        }

        if (phpb_in_module('auth')) {
            if ($this->isAuthenticated()) {
                return phpb_redirect(url: phpb_url(module: 'website_manager'));
            } else {
                Devflow::$PHP->flash->error(message: 'Access denied');
                return phpb_redirect(url: login_url());
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     * @return bool
     * @throws TypeException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Qubus\Exception\Exception
     * @throws \ReflectionException
     */
    public function isAuthenticated(): bool
    {
        return is_user_logged_in() && current_user_can(perm: 'vihzhuo:manage');
    }

    /**
     * @inheritDoc
     * @throws TypeException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ReflectionException
     */
    public function requireAuth(): ?ResponseInterface
    {
        if (!$this->isAuthenticated()) {
            return phpb_redirect(url: login_url());

        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function renderLoginForm(): ResponseInterface
    {
        return EmptyResponseFactory::create();
    }
}
