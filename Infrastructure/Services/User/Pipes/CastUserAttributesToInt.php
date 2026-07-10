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
        $body = $request->getParsedBody();
        $adminLayout = (int) $body['adminLayout'];
        $adminSideBar = (int) $body['adminSidebar'];
        $adminSkin = (int) $body['adminSkin'];

        $attribute = array_merge($body, [
            'adminLayout' => $adminLayout,
            'adminSidebar' => $adminSideBar,
            'adminSkin' => $adminSkin,
        ]);

        $request = $request->withParsedBody($attribute);

        return $next($request);
    }
}
