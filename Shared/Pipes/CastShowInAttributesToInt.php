<?php

declare(strict_types=1);

namespace App\Shared\Pipes;

use Closure;
use Psr\Http\Message\ServerRequestInterface;

class CastShowInAttributesToInt
{
    /**
     * @param ServerRequestInterface $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(ServerRequestInterface $request, Closure $next): mixed
    {
        $body = $request->getParsedBody();

        if(!isset($body['showInMenu'])){
            $showInMenu = 0;
        } else {
            $showInMenu = (int) $body['showInMenu'];
        }

        if(!isset($body['showInSearch'])){
            $showInSearch = 0;
        } else {
            $showInSearch = (int) $body['showInSearch'];
        }

        $attributes = array_merge($body, [
            'showInMenu' => $showInMenu,
            'showInSearch' => $showInSearch,
        ]);

        $request = $request->withParsedBody($attributes);

        return $next($request);
    }
}
