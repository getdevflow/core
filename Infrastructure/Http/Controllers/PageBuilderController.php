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
use Qubus\Http\Factories\EmptyResponseFactory;
use Qubus\Http\Factories\HtmlResponseFactory;
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
    public function assets(): void
    {
        $builder = $this->builder();
        $builder->handlePageBuilderAssetRequest();
    }

    /**
     * @throws TypeException
     */
    public function uploads(): void
    {
        $builder = $this->builder();
        $builder->handleUploadedFileRequest();
    }

    /**
     * @return ResponseInterface
     * @throws TypeException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ReflectionException
     */
    public function websiteManager(): ResponseInterface
    {
        if (false === current_user_can(perm: 'vihzhuo:manage')) {
            Devflow::$PHP->flash->error(
                message: trans_html('Access denied.')
            );

            return $this->redirect(admin_url());
        }

        $builder = $this->builder();
        $builder->handleRequest();

        return EmptyResponseFactory::create(200);
    }

    /**
     * @throws \Exception
     */
    public function any(ServerRequest $request): ResponseInterface
    {
        $builder = $this->builder();
        $hasPageReturned = $builder->handlePublicRequest();

        if (get_option(key: 'maintenance_mode') === 1) {
            return view(template: 'framework::maintenance');
        }

        if (empty(get_theme()) || Devflow::$PHP->configContainer->boolean(key: 'vihzhuo.enable') === false) {
            return $this->redirect(admin_url());
        }

        if ($request->getUri()->getPath() === '/' && ! $hasPageReturned) {
            return view(template: 'framework::welcome', data: ['title' => trans_html('Page Builder Welcome Page')]);
        }

        if (is_null__($hasPageReturned)) {
            return view(template: 'framework::error/404');
        }

        // @phpstan-ignore argument.type
        return HtmlResponseFactory::create($hasPageReturned);
    }

    /**
     * @throws TypeException
     */
    private function builder(): DevflowPageBuilder
    {
        return new DevflowPageBuilder(config()->array('vihzhuo'));
    }
}
