<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Vihzhuo;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qubus\Http\Response;
use Vihzhuo\Contracts\PageContract;
use Vihzhuo\Repositories\PageRepository;

use function Codefy\Framework\Helpers\view;

final class WebsiteManager extends \Vihzhuo\Modules\WebsiteManager\WebsiteManager
{
    public function handleRequest(
        ServerRequestInterface $request,
        ?string $route = null,
        ?string $action = null
    ): ResponseInterface {
        if ($action === 'destroy' && $request->getMethod() !== 'POST') {
            return new Response(status: 405, headers: ['Allow' => 'POST']);
        }

        return parent::handleRequest($request, $route, $action);
    }

    public function renderOverview(): ResponseInterface
    {
        return view('framework::backend/admin/manager/index', [
            'title' => 'Website Manager',
            'pages' => new PageRepository()->getAll(),
        ]);
    }

    public function renderPageSettings(?PageContract $page = null): ResponseInterface
    {
        return view('framework::backend/admin/manager/page-settings', [
            'title' => 'Website Manager',
            'page' => $page,
            'action' => $page === null ? 'create' : 'edit',
            'theme' => phpb_instance('theme', [phpb_config('theme'), phpb_config('theme.active_theme')]),
        ]);
    }
}
