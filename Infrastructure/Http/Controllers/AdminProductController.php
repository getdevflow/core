<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Devflow;
use App\Domain\Product\Validator\FeaturedImageValidator;
use App\Domain\Product\Model\Product;
use App\Domain\Product\Validator\DestroyProductValidator;
use App\Domain\Product\Validator\StoreProductValidator;
use App\Domain\Product\Validator\UpdateProductValidator;
use App\Infrastructure\Services\Product\Pipes\UniqueProductSlug;
use App\Infrastructure\Services\Product\ProductService;
use App\Shared\Pipes\CastShowInAttributesToInt;
use App\Shared\Pipes\CheckForScheduledStatus;
use App\Shared\Pipes\CompressUrls;
use App\Shared\Pipes\FormatCreatedDateTime;
use App\Shared\Pipes\FormatPublishedDateTime;
use App\Shared\Pipes\OptimizeFeaturedImage;
use Codefy\CommandBus\Exceptions\CommandPropertyNotFoundException;
use Codefy\CommandBus\Exceptions\UnresolvableCommandHandlerException;
use Codefy\Framework\Http\BaseController;
use Codefy\Framework\Pipeline\Pipeline;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Http\Factories\JsonResponseFactory;
use Qubus\Http\ServerRequest;
use Qubus\Http\Session\SessionException;
use ReflectionException;

use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\current_user_can;
use function App\Shared\Helpers\get_product_by_id;
use function Codefy\Framework\Helpers\logger;
use function Codefy\Framework\Helpers\trans;
use function Codefy\Framework\Helpers\view;
use function Qubus\Support\Helpers\is_false__;

final class AdminProductController extends BaseController
{
    /**
     * @param ProductService $service
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws UnresolvableQueryHandlerException
     * @throws \Exception
     */
    public function products(ProductService $service): ResponseInterface
    {
        if (false === current_user_can(perm: 'manage:products')) {
            Devflow::$PHP->flash->error(
                message: trans('Access denied.'),
            );
            return $this->redirect(admin_url());
        }

        $products = $service->findProducts();

        return view(
            template: 'framework::backend/admin/product/index',
            data: [
                'title' => trans(string: 'Products'),
                'products' => $products,
            ]
        );
    }

    /**
     * @param ServerRequest $request
     * @param ProductService $service
     * @return ResponseInterface
     * @throws Exception
     * @throws \Exception
     */
    public function productCreate(ServerRequest $request, ProductService $service): ResponseInterface
    {
        $request = Devflow::$PHP->make(name: Pipeline::class)
            ->send($request)
            ->through([
                FormatPublishedDateTime::class,
                FormatCreatedDateTime::class,
                UniqueProductSlug::class,
                CheckForScheduledStatus::class,
                OptimizeFeaturedImage::class,
                CastShowInAttributesToInt::class,
                CompressUrls::class,
            ])
            ->thenReturn();

        $id = $service->createProduct(
            data: StoreProductValidator::make(
                request: $request
            )
        );

        return $this->redirect(admin_url(path: "product/{$id}/"));
    }

    /**
     * @param ServerRequest $request
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws SessionException
     * @throws TypeException
     * @throws \Exception
     */
    public function productCreateView(ServerRequest $request): ResponseInterface
    {
        if (false === current_user_can(perm: 'create:product')) {
            Devflow::$PHP->flash->error(
                message: trans('Access denied.')
            );
            return $this->redirect(admin_url());
        }

        return view(
            template: 'framework::backend/admin/product/create',
            data: [
                'title' => trans('Create Product'),
                'request' => $request->getParsedBody(),
            ]
        );
    }

    /**
     * @param ServerRequest $request
     * @param ProductService $service
     * @param string $productId
     * @return ResponseInterface
     * @throws Exception
     */
    public function productChange(
        ServerRequest $request,
        ProductService $service,
        string $productId
    ): ResponseInterface {
        $request = Devflow::$PHP->make(name: Pipeline::class)
            ->send($request)
            ->through([
                FormatPublishedDateTime::class,
                FormatCreatedDateTime::class,
                UniqueProductSlug::class,
                CheckForScheduledStatus::class,
                OptimizeFeaturedImage::class,
                CastShowInAttributesToInt::class,
                CompressUrls::class,
            ])
            ->thenReturn();

        $service->updateProduct(
            data: UpdateProductValidator::make(
                request: $request
            )
        );

        return $this->redirect(admin_url(path: "product/{$productId}/"));
    }

    /**
     * @param ServerRequest $request
     * @param string $productId
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws SessionException
     * @throws TypeException
     * @throws \Exception
     */
    public function productView(ServerRequest $request, string $productId): ResponseInterface
    {
        if (false === current_user_can(perm: 'update:product')) {
            Devflow::$PHP->flash->error(
                message: trans('Access denied.')
            );
            return $this->redirect(admin_url());
        }

        try {
            /** @var Product $product */
            $product = get_product_by_id($productId);

            if (empty($product->id) || is_false__($product)) {
                return JsonResponseFactory::create(
                    data: trans('The product does not exist.'),
                    status: 404
                );
            }

            return view(
                template: 'framework::backend/admin/product/view',
                data: [
                    'title' => $product->title,
                    'product' => $product,
                ]
            );
        } catch (
            ContainerExceptionInterface |
            InvalidArgumentException |
            ReflectionException |
            Exception $e
        ) {
            logger('error', $e->getMessage());
        }

        return JsonResponseFactory::create(data: trans('The product does not exist.'), status: 404);
    }

    /**
     * @param ServerRequest $request
     * @param ProductService $service
     * @param string $productId
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     */
    public function removeFeaturedImage(
        ServerRequest $request,
        ProductService $service,
        string $productId
    ): ResponseInterface {
        $request = $request->withParsedBody(['id' => $productId, 'featuredImage' => '']);

        $service->removeFeaturedImage(
            data: FeaturedImageValidator::make(
                request: $request
            )
        );

        return $this->redirect($request->getHeaderLine(name: 'Referer'));
    }

    /**
     * @param ServerRequest $request
     * @param ProductService $service
     * @param string $productId
     * @return ResponseInterface
     * @throws CommandPropertyNotFoundException
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws UnresolvableCommandHandlerException
     */
    public function productDelete(ServerRequest $request, ProductService $service, string $productId): ResponseInterface
    {
        $request = $request->withParsedBody(['id' => $productId]);

        $service->deleteProduct(
            data: DestroyProductValidator::make(
                request: $request
            )
        );

        return $this->redirect(admin_url('product/'));
    }
}
