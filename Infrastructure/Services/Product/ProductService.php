<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Product;

use App\Application\Devflow;
use App\Domain\Product\Command\RemoveFeaturedImageCommand;
use App\Domain\Product\Validator\FeaturedImageValidator;
use App\Domain\Product\Command\CreateProductCommand;
use App\Domain\Product\Command\DeleteProductCommand;
use App\Domain\Product\Command\UpdateProductCommand;
use App\Domain\Product\Model\Product;
use App\Domain\Product\Validator\DestroyProductValidator;
use App\Domain\Product\Validator\StoreProductValidator;
use App\Domain\Product\Validator\UpdateProductValidator;
use App\Infrastructure\Persistence\Cache\ProductCachePsr16;
use App\Infrastructure\Services\Product\Event\ProductCreated;
use App\Infrastructure\Services\Product\Event\ProductDeleted;
use App\Infrastructure\Services\Product\Event\ProductUpdated;
use Codefy\CommandBus\Exceptions\CommandPropertyNotFoundException;
use Codefy\CommandBus\Exceptions\UnresolvableCommandHandlerException;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
use Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use ReflectionException;

use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\get_current_user_id;
use function App\Shared\Helpers\get_product_by_id;
use function App\Shared\Helpers\get_products;
use function Codefy\Framework\Helpers\abort;
use function Codefy\Framework\Helpers\command;
use function Codefy\Framework\Helpers\logger;
use function Qubus\Security\Helpers\t__;
use function Qubus\Support\Helpers\is_false__;

final readonly class ProductService
{
    public function __construct(private EventDispatcherInterface $event)
    {
    }

    /**
     * @return array
     * @throws ReflectionException
     * @throws UnresolvableQueryHandlerException
     */
    public function findProducts(): array
    {
        return get_products();
    }

    /**
     * @param string $id
     * @return Product
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws \Qubus\Exception\Exception
     */
    public function findById(string $id): Product
    {
        /** @var Product $product */
        $product = get_product_by_id($id);
        if (empty($product->id) || is_false__($product)) {
            abort(
                code: 404,
                uri: admin_url(),
                message: t__(msgid: 'The product does not exist.', domain: 'devflow')
            );
        }

        return $product;
    }

    /**
     * @param StoreProductValidator $data
     * @return string
     * @throws Exception
     */
    public function createProduct(StoreProductValidator $data): string
    {
        try {
            command(
                command: new CreateProductCommand(
                    data: $data->toDtoArray()
                )
            );

            /** @var Product $product */
            $product = get_product_by_id($data->toDtoArray()['id']->toNative());

            $this->event->dispatch(new ProductCreated($product->toArray(), get_current_user_id()));

            Devflow::$PHP->flash->success(
                message: t__(msgid: 'Product added successfully.', domain: 'devflow'),
            );
        } catch (
            ContainerExceptionInterface |
            InvalidArgumentException |
            NotFoundExceptionInterface |
            \Qubus\Exception\Exception |
            CommandPropertyNotFoundException |
            \ReflectionException |
            UnresolvableCommandHandlerException $e
        ) {
            logger(level: 'error', message: $e->getMessage(), context: ['ProductService' => 'createProduct']);

            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Could not create product. Please try again later.', domain: 'devflow'),
            );
        }

        return $data->validated()['id'];
    }

    public function updateProduct(UpdateProductValidator $data): void
    {
        try {
            command(
                command: new UpdateProductCommand(
                    data: $data->toDtoArray()
                )
            );

            /** @var Product $product */
            $product = get_product_by_id($data->toDtoArray()['id']->toNative());

            $this->event->dispatch(new ProductUpdated($product->toArray(), get_current_user_id()));

            Devflow::$PHP->flash->success(Devflow::$PHP->flash->notice(num: 200));
        } catch (
            CommandPropertyNotFoundException |
            InvalidArgumentException |
            NotFoundExceptionInterface |
            ContainerExceptionInterface |
            UnresolvableCommandHandlerException |
            ReflectionException |
            \Qubus\Exception\Exception $e
        ) {
            logger('error', $e->getMessage());

            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Change exception occurred and was logged.', domain: 'devflow')
            );
        }
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws \Qubus\Exception\Exception
     */
    public function removeFeaturedImage(FeaturedImageValidator $data): void
    {
        try {
            command(
                command: new RemoveFeaturedImageCommand(
                    data: $data->toDtoArray()
                )
            );

            /** @var Product $product */
            $product = get_product_by_id($data->toDtoArray()['id']->toNative());

            $this->event->dispatch(new ProductUpdated($product->toArray(), get_current_user_id()));

            Devflow::$PHP->flash->success(
                message: t__(msgid: 'Removal of featured image was successful.', domain: 'devflow')
            );
        } catch (
            CommandPropertyNotFoundException |
            UnresolvableCommandHandlerException |
            ReflectionException $e
        ) {
            logger(level: 'error', message: $e->getMessage(), context: ['ProductService' => 'removeFeaturedImage']);

            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Removal exception occurred and was logged.', domain: 'devflow')
            );
        }
    }

    /**
     * @throws UnresolvableCommandHandlerException
     * @throws ContainerExceptionInterface
     * @throws CommandPropertyNotFoundException
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     * @throws \Qubus\Exception\Exception
     * @throws ReflectionException
     * @throws TypeException
     */
    public function deleteProduct(DestroyProductValidator $data): void
    {
        /** @var string $productId */
        $productId = $data->toDtoArray()['id']->toNative();
        /** @var Product $product */
        $product = get_product_by_id($productId);

        try {
            command(
                command: new DeleteProductCommand(
                    data: $data->toDtoArray()
                )
            );

            ProductCachePsr16::clean($product->toArray());

            $this->event->dispatch(new ProductDeleted($productId, get_current_user_id()));

            Devflow::$PHP->flash->success(
                message: t__(msgid: 'Removal was successful.', domain: 'devflow')
            );
        } catch (
            CommandPropertyNotFoundException |
            UnresolvableCommandHandlerException |
            ReflectionException $e
        ) {
            logger('error', $e->getMessage());

            Devflow::$PHP->flash->error(
                message: t__(msgid: 'A deletion exception occurred and was logged.', domain: 'devflow')
            );
        }
    }
}
