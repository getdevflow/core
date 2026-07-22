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
use Vihzhuo\Contracts\AuthContract;

use function App\Shared\Helpers\current_user_can;
use function App\Shared\Helpers\is_user_logged_in;
use function Codefy\Framework\Helpers\config;
use function Codefy\Framework\Helpers\site_url;
use function phpb_redirect;

class VihzhuoAuth implements AuthContract
{
    /**
     * @inheritDoc
     */
    public function handleRequest(?string $action = null): void
    {
        if (phpb_in_module('auth')) {
            if ($this->isAuthenticated()) {
                phpb_redirect(url: phpb_url(module: 'website_manager'));
            } else {
                Devflow::$PHP->flash->error(message: 'Access denied');
                phpb_redirect(url: site_url(path: 'login'));
            }
        } elseif ($action === 'logout') {
            phpb_redirect(url: site_url(path: 'logout'));
        }
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
    public function requireAuth(): void
    {
        if (!$this->isAuthenticated()) {
            phpb_redirect(
                url: site_url(
                    path: config()->string(key: 'auth.login_route')
                )
            );
            exit();
        }
    }

    /**
     * @inheritDoc
     */
    public function renderLoginForm(): void
    {
        return ;
    }
}
