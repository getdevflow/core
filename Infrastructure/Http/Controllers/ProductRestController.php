<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use Codefy\Framework\Http\BaseController;
use Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Http\Factories\JsonResponseFactory;
use Qubus\Http\Request;
use ReflectionException;

use function App\Shared\Helpers\cms_delete_product;
use function App\Shared\Helpers\cms_insert_product;
use function App\Shared\Helpers\cms_update_product;
use function App\Shared\Helpers\get_all_products_with_filters;
use function App\Shared\Helpers\get_product_by_id;
use function array_merge;
use function Codefy\Framework\Helpers\trans_html;
use function is_object;
use function is_string;
use function Qubus\Error\Helpers\is_error;
use function Qubus\Support\Helpers\is_false__;
use function Qubus\Support\Helpers\is_null__;

final class ProductRestController extends BaseController
{
    /**
     * @uses \App\Shared\Helpers\get_all_products_with_filters()
     * @throws Exception
     */
    public function index(Request $request): ResponseInterface
    {
        $productSku = null;
        $limit = 0;
        $offset = null;
        $status = 'all';

        $handler = $request->handler();

        if (!is_null__($handler->get(index: 'sku')) && is_string($handler->get(index: 'sku')->getValue())) {
            $productSku = $handler->get(index: 'sku')->getValue();
        }
        if (!is_null__($handler->get(index: 'limit')) && is_string($handler->get(index: 'limit')->getValue())) {
            $limit = (int) $handler->get(index: 'limit')->getValue();
        }
        if (!is_null__($handler->get(index: 'offset')) && is_string($handler->get(index: 'offset')->getValue())) {
            $offset = (int) $handler->get(index: 'offset')->getValue();
        }
        if (!is_null__($handler->get(index: 'status')) && is_string($handler->get(index: 'status')->getValue())) {
            $status = $handler->get(index: 'status')->getValue();
        }

        try {
            $products = get_all_products_with_filters(
                productSku: $productSku,
                limit: $limit,
                offset: $offset,
                status: $status
            );

            if (empty($products)) {
                return JsonResponseFactory::create(trans_html('No data.'), 404);
            }
        } catch (Exception $e) {
            return JsonResponseFactory::create($e->getMessage(), 400);
        }

        return JsonResponseFactory::create($products);
    }

    /**
     * @uses \App\Shared\Helpers\get_product_by_id()
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function show(string $id): ResponseInterface
    {
        try {
            $product = get_product_by_id($id);

            if (is_false__($product)) {
                return JsonResponseFactory::create(trans_html('No data.'), 404);
            }
        } catch (NotFoundExceptionInterface $e) {
            return JsonResponseFactory::create($e->getMessage(), 400);
        }

        return JsonResponseFactory::create($product);
    }

    /**
     * @uses \App\Shared\Helpers\cms_insert_product()
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function store(Request $request): ResponseInterface
    {
        try {
            $create = cms_insert_product($request->handler()->all());
            if (is_error($create)) {
                return JsonResponseFactory::create($create->getMessage(), 400);
            }
            if (is_null__($create)) {
                return JsonResponseFactory::create(trans_html('No data.'), 404);
            }
        } catch (Exception $e) {
            return JsonResponseFactory::create($e->getMessage(), 400);
        }

        return JsonResponseFactory::create($create);
    }

    /**
     * @uses \App\Shared\Helpers\cms_update_product()
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function update(string $id, Request $request): ResponseInterface
    {
        $array = array_merge(['id' => $id], $request->handler()->all());
        try {
            $update = cms_update_product($array);
            if (is_error($update)) {
                return JsonResponseFactory::create($update->getMessage(), 400);
            }

            if (is_null__($update)) {
                return JsonResponseFactory::create(trans_html('No data.'), 404);
            }
        } catch (Exception $e) {
            return JsonResponseFactory::create($e->getMessage(), 400);
        }

        return JsonResponseFactory::create($update);
    }

    /**
     * @uses \App\Shared\Helpers\cms_delete_product()
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function destroy(string $id): ResponseInterface
    {
        try {
            $delete = cms_delete_product($id);
            if (is_false__($delete)) {
                return JsonResponseFactory::create(trans_html('No data.'));
            }

            if (is_object($delete) && $delete->id === $id) {
                return JsonResponseFactory::create(trans_html('Resource deleted.'));
            }
        } catch (Exception $e) {
            return JsonResponseFactory::create($e->getMessage(), 400);
        }

        return JsonResponseFactory::create(trans_html('Bad request.'));
    }
}
