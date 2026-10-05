<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Devflow;
use App\Infrastructure\Services\Vihzhuo\DevflowPageBuilder;
use Codefy\Framework\Http\BaseController;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Http\ServerRequest;
use ReflectionException;

use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\current_user_can;
use function App\Shared\Helpers\get_option;
use function App\Shared\Helpers\get_theme;
use function Codefy\Framework\Helpers\config;
use function Codefy\Framework\Helpers\trans_html;
use function Codefy\Framework\Helpers\view;
use function Qubus\Support\Helpers\is_null__;

final class PageBuilderController extends BaseController
{
    /**
     * @throws \Exception
     */
    public function assets(ServerRequest $request): ResponseInterface
    {
        \Vihzhuo\Core\HttpContext::setRequest($request);
        $builder = $this->builder();
        return $builder->handlePageBuilderAssetRequest();
    }

    /**
     * @throws TypeException
     * @throws \Exception
     */
    public function uploads(ServerRequest $request): ResponseInterface
    {
        \Vihzhuo\Core\HttpContext::setRequest($request);
        $builder = $this->builder();
        return $builder->handleUploadedFileRequest();
    }

    /**
     * @param ServerRequest $request
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Exception
     */
    public function websiteManager(ServerRequest $request): ResponseInterface
    {
        if (false === current_user_can(perm: 'vihzhuo:manage')) {
            Devflow::$PHP->flash->error(
                message: trans_html('Access denied.')
            );

            return $this->redirect(admin_url());
        }

        $builder = $this->builder();
        return $builder->handleRequest($request);
    }

    /**
     * @throws \Exception
     */
    public function any(ServerRequest $request): ResponseInterface
    {
        if (get_option(key: 'maintenance_mode') === 1) {
            return view(template: 'framework::maintenance')
                ->withStatus(503)
                ->withHeader('Retry-After', config()->string('cms.maintenance_mode_attrs.retry_after', '3600'))
                ->withHeader('Cache-Control', config()->string(
                    'cms.maintenance_mode_attrs.cache_control',
                    'no-cache, no-store, must-revalidate'
                ))
                ->withHeader('Pragma', config()->string('cms.maintenance_mode_attrs.pragma', 'no-cache'))
                ->withHeader('Expires', config()->string('cms.maintenance_mode_attrs.expires', '0'));
        }

        if (empty(get_theme()) || Devflow::$PHP->configContainer->boolean(key: 'vihzhuo.enable') === false) {
            return $this->redirect(admin_url());
        }

        $hasPageReturned = $this->builder()->handlePublicRequest($request);

        if ($request->getUri()->getPath() === '/' && ! $hasPageReturned) {
            return view(template: 'framework::welcome', data: ['title' => trans_html('Page Builder Welcome Page')]);
        }

        if (is_null__($hasPageReturned)) {
            return view(template: 'framework::error/404')->withStatus(404);
        }

        return $hasPageReturned;
    }

    /**
     * @throws TypeException
     */
    private function builder(): DevflowPageBuilder
    {
        return new DevflowPageBuilder(config()->array('vihzhuo'));
    }
}
