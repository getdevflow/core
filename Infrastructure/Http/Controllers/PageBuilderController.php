<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Infrastructure\Services\Vihzhuo\DevflowPageBuilder;
use Codefy\Framework\Http\BaseController;
use Psr\Http\Message\ResponseInterface;
use Qubus\Exception\Data\TypeException;
use Qubus\Http\Factories\EmptyResponseFactory;
use Qubus\Http\Factories\HtmlResponseFactory;
use Qubus\Http\ServerRequest;

use function Codefy\Framework\Helpers\config;
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
     * @throws TypeException
     */
    public function websiteManager(): ResponseInterface
    {
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

        if ($request->getUri()->getPath() === '/' && ! $hasPageReturned) {
            return view(template: 'framework::welcome');
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
    protected function builder(): DevflowPageBuilder
    {
        return new DevflowPageBuilder(config()->array('vihzhuo'));
    }
}
