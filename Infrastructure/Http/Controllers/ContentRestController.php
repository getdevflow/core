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

use function App\Shared\Helpers\cms_delete_content;
use function App\Shared\Helpers\cms_insert_content;
use function App\Shared\Helpers\cms_update_content;
use function App\Shared\Helpers\get_all_content_with_filters;
use function App\Shared\Helpers\get_content_by_id;
use function array_merge;
use function Codefy\Framework\Helpers\trans_html;
use function is_object;
use function is_string;
use function Qubus\Error\Helpers\is_error;
use function Qubus\Support\Helpers\is_false__;
use function Qubus\Support\Helpers\is_null__;

final class ContentRestController extends BaseController
{
    /**
     * @uses \App\Shared\Helpers\get_all_content_with_filters()
     * @throws Exception
     */
    public function index(Request $request): ResponseInterface
    {
        $contentType = null;
        $limit = 0;
        $offset = null;
        $status = 'all';

        $handler = $request->handler();

        if (!is_null__($handler->get(index: 'type')) && is_string($handler->get(index: 'type')->getValue())) {
            $contentType = $handler->get(index: 'type')->getValue();
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
            $content = get_all_content_with_filters(
                contentTypeSlug: $contentType,
                limit: $limit,
                offset: $offset,
                status: $status
            );

            if (empty($content)) {
                return JsonResponseFactory::create(trans_html('No data.'), 404);
            }
        } catch (Exception $e) {
            return JsonResponseFactory::create($e->getMessage(), 400);
        }

        return JsonResponseFactory::create($content);
    }

    /**
     * @uses \App\Shared\Helpers\get_content_by()
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
            $content = get_content_by_id($id);

            if (is_false__($content)) {
                return JsonResponseFactory::create(trans_html('No data.'), 404);
            }
        } catch (NotFoundExceptionInterface $e) {
            return JsonResponseFactory::create($e->getMessage(), 400);
        }

        return JsonResponseFactory::create($content);
    }

    /**
     * @uses \App\Shared\Helpers\cms_insert_content()
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function store(Request $request): ResponseInterface
    {
        try {
            $create = cms_insert_content($request->handler()->all());
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
     * @uses \App\Shared\Helpers\cms_update_content()
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
            $update = cms_update_content($array);
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
     * @uses \App\Shared\Helpers\cms_delete_content()
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function destroy(string $id): ResponseInterface
    {
        try {
            $delete = cms_delete_content($id);
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
