<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Content\Pipes;

use Closure;
use Psr\Http\Message\ServerRequestInterface;

class CastSidebarAttributeToInt
{
    /**
     * @param ServerRequestInterface $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(ServerRequestInterface $request, Closure $next): mixed
    {
        $body = $request->getParsedBody();

        if(!isset($body['sidebar'])){
            $sidebar = 0;
        } else {
            $sidebar = (int) $body['sidebar'];
        }

        $attribute = array_merge($body, [
            'sidebar' => $sidebar,
        ]);

        $request = $request->withParsedBody($attribute);

        return $next($request);
    }
}
