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

use function App\Shared\Helpers\cms_delete_user;
use function App\Shared\Helpers\cms_insert_user;
use function App\Shared\Helpers\cms_update_user;
use function App\Shared\Helpers\get_all_users;
use function App\Shared\Helpers\get_user_by;
use function array_merge;
use function Qubus\Error\Helpers\is_error;
use function Qubus\Security\Helpers\t__;
use function Qubus\Support\Helpers\is_false__;
use function Qubus\Support\Helpers\is_null__;
use function Qubus\Support\Helpers\is_true__;

final class UserRestController extends BaseController
{
    /**
     * @throws Exception
     */
    public function index(Request $request): ResponseInterface
    {
        try {
            $users = get_all_users();

            if (empty($users)) {
                return JsonResponseFactory::create(t__(msgid: 'No data.', domain: 'devflow'), 404);
            }
        } catch (Exception $e) {
            return JsonResponseFactory::create($e->getMessage(), 400);
        }

        return JsonResponseFactory::create($users);
    }

    /**
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
            $user = get_user_by('id', $id);

            if (is_false__($user)) {
                return JsonResponseFactory::create(t__(msgid: 'No data.', domain: 'devflow'), 404);
            }
        } catch (NotFoundExceptionInterface $e) {
            return JsonResponseFactory::create($e->getMessage(), 400);
        }

        return JsonResponseFactory::create($user);
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     */
    public function store(Request $request): ResponseInterface
    {
        try {
            $create = cms_insert_user($request->handler()->all());
            if (is_error($create)) {
                return JsonResponseFactory::create($create->getMessage(), 400);
            }
            if (is_null__($create)) {
                return JsonResponseFactory::create(t__(msgid: 'No data.', domain: 'devflow'), 404);
            }
        } catch (Exception $e) {
            return JsonResponseFactory::create($e->getMessage(), 400);
        }

        return JsonResponseFactory::create($create);
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     */
    public function update(string $id, Request $request): ResponseInterface
    {
        $array = array_merge(['id' => $id], $request->handler()->all());
        try {
            $update = cms_update_user($array);
            if (is_error($update)) {
                return JsonResponseFactory::create($update->getMessage(), 400);
            }

            if (is_null__($update)) {
                return JsonResponseFactory::create(t__(msgid: 'No data.', domain: 'devflow'), 404);
            }
        } catch (Exception $e) {
            return JsonResponseFactory::create($e->getMessage(), 400);
        }

        return JsonResponseFactory::create($update);
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function destroy(string $id): ResponseInterface
    {
        try {
            $delete = cms_delete_user($id);
            if (is_false__($delete)) {
                return JsonResponseFactory::create(t__(msgid: 'No data.', domain: 'devflow'));
            }

            if (is_true__($delete)) {
                return JsonResponseFactory::create(t__(msgid: 'Resource deleted.', domain: 'devflow'));
            }
        } catch (Exception $e) {
            return JsonResponseFactory::create($e->getMessage(), 400);
        }

        return JsonResponseFactory::create(t__(msgid: 'Bad request.', domain: 'devflow'));
    }
}
