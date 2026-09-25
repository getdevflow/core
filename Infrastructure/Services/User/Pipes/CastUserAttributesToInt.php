<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\User\Pipes;

use Closure;
use Psr\Http\Message\ServerRequestInterface;

class CastUserAttributesToInt
{
    /**
     * @param ServerRequestInterface $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(ServerRequestInterface $request, Closure $next): mixed
    {
        $body = (array) $request->getParsedBody();
        $adminLayout = (int) ($body['adminLayout'] ?? 0);
        $adminSideBar = (int) ($body['adminSidebar'] ?? 0);
        $adminSkin = (int) ($body['adminSkin'] ?? 0);

        $attribute = array_merge($body, [
            'adminLayout' => $adminLayout,
            'adminSidebar' => $adminSideBar,
            'adminSkin' => $adminSkin,
        ]);

        $request = $request->withParsedBody($attribute);

        return $next($request);
    }
}
