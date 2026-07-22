<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middlewares;

use App\Domain\Site\Model\Site;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function Codefy\Framework\Helpers\view;

class ArchivedSiteMiddleware implements MiddlewareInterface
{
    /**
     * @inheritDoc
     * @throws Exception
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $site = $request->getAttribute('site');
        $path = $request->getUri()->getPath();

        if (! $site instanceof Site) {
            return view(template: 'framework::error/404')->withStatus(404);
        }

        if (str_contains($path, '/admin/')) {
            return $handler->handle($request);
        }

        if ($site->status !== 'archive') {
            return $handler->handle($request);
        }

        $siteName = $site->name ?? null;

        return view(
            template: 'framework::site-archived',
            data: [
                'title' => $siteName,
            ]
        )->withStatus(410);
    }
}
