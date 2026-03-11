<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Domain\User\Model\User;
use Codefy\Framework\Auth\Rbac\Entity\Role;
use Codefy\Framework\Auth\Rbac\Rbac;
use Codefy\Framework\Auth\Repository\AuthUserRepository;
use Codefy\Framework\Factory\FileLoggerFactory;
use Codefy\Framework\Http\Middleware\Auth\UserAuthorizationMiddleware;
use Codefy\Framework\Http\Middleware\Auth\UserCookieDecryptMiddleware;
use Codefy\Framework\Http\RequestContext;
use Psr\Http\Message\ServerRequestInterface;
use Qubus\Exception\Data\TypeException;

use function App\Shared\Helpers\get_user_by;
use function Qubus\Support\Helpers\is_false__;

final class UserAuth
{
    public function __construct(protected Rbac $rbac, protected AuthUserRepository $user)
    {
    }

    /**
     * Authorization check.
     *
     * @param string $permissionName
     * @param array<array-key, mixed> $ruleParams
     * @return bool
     * @throws \ReflectionException
     * @throws TypeException
     */
    public function can(string $permissionName, array $ruleParams = []): bool
    {
        if (
                $this->getRequest()->getHeaderLine(
                        UserAuthorizationMiddleware::HEADER_HTTP_STATUS_CODE
                ) === 'not_authorized'
        ) {
            return false;
        }

        if (!$this->hasAuthenticatedUser()) {
            return false;
        }

        $roles = $this->getRoles();

        return array_any($roles, fn($role) => $role->checkAccess($permissionName, $ruleParams));
    }

    /**
     * Get the current authenticated user model.
     *
     * @return User|null
     * @throws \ReflectionException
     */
    public function current(): ?User
    {
        $token = $this->getToken();

        if ($token === null) {
            return null;
        }

        return $this->resolveUserByToken($token);
    }

    /**
     * Whether user is authenticated.
     *
     * @return bool
     */
    private function hasAuthenticatedUser(): bool
    {
        return $this->getToken() !== null;
    }

    /**
     * @throws \ReflectionException
     */
    private function resolveUserByToken(string $token): ?User
    {
        try {
            /** @var User $user */
            $user = get_user_by('token', $token);
            if (is_false__($user)) {
                return null;
            }

            return $user;
        } catch (\Throwable $e) {
            FileLoggerFactory::getLogger()->error($e->getMessage());
            return null;
        }
    }

    /**
     * @return array<string, Role>
     * @throws \ReflectionException
     */
    private function getRoles(): array
    {
        $user = $this->current();

        if ($user === false) {
            return [];
        }

        $roles = [];
        // @phpstan-ignore property.nonObject
        foreach ((array)$user->role as $roleName) {
            if ($role = $this->rbac->getRole($roleName)) {
                $roles[$roleName] = $role;
            }
        }

        return $roles;
    }

    /**
     * A guest is any user without an authenticated token.
     */
    public function guest(): bool
    {
        return $this->getToken() === null;
    }

    /**
     * Whether user is logged in.
     *
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        return $this->getToken() !== null;
    }

    /**
     * Fetch decrypted token from request context.
     */
    private function getToken(): ?string
    {
        $request = $this->getRequest();

        return $request->getAttribute(UserCookieDecryptMiddleware::USER_COOKIE);
    }

    /**
     * Return request object.
     *
     * @return ServerRequestInterface
     */
    private function getRequest(): ServerRequestInterface
    {
        return RequestContext::get();
    }
}
