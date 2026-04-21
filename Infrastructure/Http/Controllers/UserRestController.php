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

use function App\Shared\Helpers\cms_insert_user;
use function App\Shared\Helpers\cms_update_user;
use function App\Shared\Helpers\get_all_users;
use function App\Shared\Helpers\get_current_site_id;
use function App\Shared\Helpers\get_user_by;
use function App\Shared\Helpers\remove_user_from_site;
use function array_merge;
use function Qubus\Error\Helpers\is_error;
use function Qubus\Security\Helpers\t__;
use function Qubus\Support\Helpers\is_false__;
use function Qubus\Support\Helpers\is_null__;

final class UserRestController extends BaseController
{
    /**
     * @uses \App\Shared\Helpers\get_all_users()
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
     * @uses \App\Shared\Helpers\get_user_by()
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
     * @uses \App\Shared\Helpers\cms_insert_user()
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
     * @uses \App\Shared\Helpers\cms_update_user()
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
     * @uses \App\Shared\Helpers\remove_user_from_site()
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function remove(string $id): ResponseInterface
    {
        try {
            remove_user_from_site(
                userId: $id,
                params: [
                    'site_id' => get_current_site_id(),
                    'assign_id' => null,
                    'role' => null,
                ]
            );
        } catch (Exception $e) {
            return JsonResponseFactory::create(t__(msgid: 'Bad request.', domain: 'devflow'));
        }

        return JsonResponseFactory::create(t__(msgid: 'User removed from site.', domain: 'devflow'));
    }
}
