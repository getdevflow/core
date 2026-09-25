<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Vihzhuo;

use Codefy\Framework\Http\Middleware\Csrf\CsrfTokenMiddleware;
use Codefy\Framework\Http\RequestContext;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qubus\Exception\Data\TypeException;
use Qubus\Http\Response;
use Vihzhuo\Contracts\PageContract;
use Vihzhuo\Modules\GrapesJS\PageBuilder;

use function Codefy\Framework\Helpers\config;

final class PageEditor extends PageBuilder
{
    public function handleRequest(
        ServerRequestInterface $request,
        ?string $route = null,
        ?string $action = null,
        ?PageContract $page = null
    ): ?ResponseInterface {
        if (in_array($action, ['store', 'upload', 'upload_delete'], true) && $request->getMethod() !== 'POST') {
            return new Response(status: 405, headers: ['Allow' => 'POST']);
        }

        return parent::handleRequest($request, $route, $action, $page);
    }

    /**
     * @throws TypeException
     * @throws Exception
     */
    public function customScripts(string $location, ?string $scripts = null): string
    {
        $custom = parent::customScripts($location, $scripts);
        if ($location !== 'head' || $scripts !== null) {
            return $custom;
        }

        $header = json_encode(config()->string('csrf.header'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR);
        $token = json_encode(
            RequestContext::get()->getAttribute(CsrfTokenMiddleware::CSRF_SESSION_ATTRIBUTE),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR
        );

        return $custom . <<<HTML
<script>
(() => {
    const header = {$header}, token = {$token};
    const sameOrigin = url => new URL(url, window.location.href).origin === window.location.origin;
    if (window.jQuery) {
        window.jQuery.ajaxPrefilter((options, original, xhr) => {
            if (token && sameOrigin(options.url)) xhr.setRequestHeader(header, token);
        });
    }
    window.addEventListener('vihzhuo:grapesjs:before-init', event => {
        const assets = event.detail.assetManager;
        if (token && assets && assets.upload && sameOrigin(assets.upload)) {
            assets.headers = Object.assign({}, assets.headers, { [header]: token });
        }
    });
})();
</script>
HTML;
    }
}
