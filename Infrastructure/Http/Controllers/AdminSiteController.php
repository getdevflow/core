<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Devflow;
use App\Domain\Site\Model\Site;
use App\Domain\User\Query\FindMultisiteUniqueUsersQuery;
use Codefy\CommandBus\Exceptions\CommandPropertyNotFoundException;
use Codefy\CommandBus\Exceptions\UnresolvableCommandHandlerException;
use Codefy\Framework\Http\BaseController;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
use DateInvalidTimeZoneException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Http\Factories\JsonResponseFactory;
use Qubus\Http\ServerRequest;
use ReflectionException;

use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\cms_delete_site;
use function App\Shared\Helpers\cms_delete_site_user;
use function App\Shared\Helpers\cms_insert_site;
use function App\Shared\Helpers\cms_update_site;
use function App\Shared\Helpers\current_user_can;
use function App\Shared\Helpers\get_all_sites;
use function App\Shared\Helpers\get_site_by;
use function App\Shared\Helpers\is_multisite;
use function App\Shared\Helpers\sort_list;
use function Codefy\Framework\Helpers\ask;
use function Codefy\Framework\Helpers\config;
use function Codefy\Framework\Helpers\logger;
use function Codefy\Framework\Helpers\view;
use function Qubus\Error\Helpers\is_error;
use function Qubus\Security\Helpers\t__;
use function Qubus\Support\Helpers\is_false__;
use function sprintf;

final class AdminSiteController extends BaseController
{
    /**
     * @param ServerRequest $request
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws DateInvalidTimeZoneException
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function siteCreate(ServerRequest $request): ResponseInterface
    {
        if (false === current_user_can(perm: 'create:sites')) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Access denied.', domain: 'devflow')
            );
            return $this->redirect(admin_url());
        }

        if (!is_multisite()) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Multisite is not enabled.', domain: 'devflow')
            );
            return $this->redirect(admin_url());
        }

        try {
            $id = cms_insert_site($request);
            if (is_error($id)) {
                Devflow::$PHP->flash->error(
                    message: t__(msgid: 'Insertion error occurred.', domain: 'devflow')
                );
            }

            Devflow::$PHP->flash->success(Devflow::$PHP->flash->notice(num: 201));
        } catch (
            CommandPropertyNotFoundException |
            ContainerExceptionInterface |
            InvalidArgumentException |
            UnresolvableCommandHandlerException |
            TypeException |
            Exception |
            ReflectionException $e
        ) {
            logger(level: 'error', message: $e->getMessage());
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Insertion exception occurred and was logged.', domain: 'devflow')
            );
        }

        return $this->redirect($request->getHeaderLine('Referer'));
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
    public function sites(ServerRequest $request): ResponseInterface
    {
        if (false === current_user_can(perm: 'manage:sites')) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Access denied.', domain: 'devflow')
            );
            return $this->redirect(admin_url());
        }

        if (!is_multisite()) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Multisite is not enabled.', domain: 'devflow')
            );
            return $this->redirect(admin_url());
        }

        try {
            $connection = config()->string(key: 'database.default');

            $sites = get_all_sites();

            return view(
                template: 'framework::backend/admin/site/index',
                data: [
                    'title' => t__(msgid: 'Sites', domain: 'devflow'),
                    'sites' => $sites,
                    'request' => $request,
                    'connection' => $connection,
                ]
            );
        } catch (UnresolvableQueryHandlerException | ReflectionException $e) {
            logger(level: 'error', message: $e->getMessage());
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Exception occurred and was logged.', domain: 'devflow')
            );
        }

        return JsonResponseFactory::create(data: t__(msgid: 'Content types error.', domain: 'devflow'), status: 404);
    }

    /**
     * @param ServerRequest $request
     * @param string $siteId
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function siteChange(ServerRequest $request, string $siteId): ResponseInterface
    {
        if (false === current_user_can(perm: 'update:sites')) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Access denied.', domain: 'devflow')
            );
            return $this->redirect(admin_url());
        }

        if (!is_multisite()) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Multisite is not enabled.', domain: 'devflow')
            );
            return $this->redirect(admin_url());
        }

        $dataArrayMerge = array_merge(['id' => $siteId], $request->getParsedBody());

        try {
            $id = cms_update_site($dataArrayMerge);
            if (is_error($id)) {
                Devflow::$PHP->flash->error(
                    message: t__(msgid: 'Change error occurred.', domain: 'devflow')
                );
            }

            Devflow::$PHP->flash->success(Devflow::$PHP->flash->notice(num: 200));
        } catch (
            CommandPropertyNotFoundException |
            ContainerExceptionInterface |
            DateInvalidTimeZoneException |
            InvalidArgumentException |
            UnresolvableCommandHandlerException |
            TypeException |
            Exception |
            ReflectionException $e
        ) {
            logger(level: 'error', message: $e->getMessage());
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Change exception occurred and was logged.', domain: 'devflow')
            );
        }

        return $this->redirect($request->getHeaderLine('Referer'));
    }

    /**
     * @param string $siteId
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Exception
     */
    public function siteView(string $siteId): ResponseInterface
    {
        if (false === current_user_can(perm: 'manage:sites')) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Access denied.', domain: 'devflow')
            );
            return $this->redirect(admin_url());
        }

        if (!is_multisite()) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Multisite is not enabled.', domain: 'devflow')
            );
            return $this->redirect(admin_url());
        }

        try {
            /** @var Site $site */
            $site = get_site_by(field: 'id', value: $siteId);

            if (is_false__($site)) {
                return JsonResponseFactory::create(
                    data: t__(msgid: 'The site does not exist.', domain: 'devflow'),
                    status: 404
                );
            }

            return view(
                template: 'framework::backend/admin/site/view',
                data: [
                    'title' => $site->name,
                    'site' => $site,
                ]
            );
        } catch (
            TypeException |
            ReflectionException $e
        ) {
            logger(level: 'error', message: $e->getMessage());
        }

        return JsonResponseFactory::create(
            data: t__(msgid: 'The site does not exist.', domain: 'devflow'),
            status: 404
        );
    }

    /**
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
    public function siteUsers(): ResponseInterface
    {
        if (false === current_user_can(perm: 'manage:sites')) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Access denied.', domain: 'devflow')
            );
            return $this->redirect(admin_url());
        }

        if (!is_multisite()) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Multisite is not enabled.', domain: 'devflow')
            );
            return $this->redirect(admin_url());
        }
        
        $results = ask(new FindMultisiteUniqueUsersQuery());

        $users = sort_list($results, 'lname', 'ASC', true);

        return view(
            template: 'framework::backend/admin/site/users',
            data: [
                'title' => t__(msgid: 'Manage System Users', domain: 'devflow'),
                'users' => $users,
            ]
        );
    }

    /**
     * @param ServerRequest $request
     * @param string $userId
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function siteUsersDelete(ServerRequest $request, string $userId): ResponseInterface
    {
        if (
            false === current_user_can(perm: 'delete:users') &&
            false === current_user_can(perm: 'manage:sites')
        ) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Access denied.', domain: 'devflow')
            );
            return $this->redirect(admin_url('site/users/'));
        }

        if (!is_multisite()) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Multisite is not enabled.', domain: 'devflow')
            );
            return $this->redirect(admin_url());
        }

        try {
            $siteUser = cms_delete_site_user(
                $request->getParsedBody()['user_id'],
                [
                    'assign_id' => $request->getParsedBody()['assign_id'],
                    'role' => $request->getParsedBody()['role'],
                ]
            );

            if (is_error($siteUser)) {
                Devflow::$PHP->flash->error(
                    sprintf(
                        'ERROR[%s]: %s',
                        $siteUser->getCode(),
                        $siteUser->getMessage()
                    ),
                );
            } else {
                Devflow::$PHP->flash->success(Devflow::$PHP->flash->notice(num: 200));
            }
        } catch (
            CommandPropertyNotFoundException |
            Exception |
            UnresolvableQueryHandlerException |
            NotFoundExceptionInterface |
            ContainerExceptionInterface |
            ReflectionException $e
        ) {
            logger(level: 'error', message: $e->getMessage());
            Devflow::$PHP->flash->error(
                t__(msgid: 'Delete exception occurred and was logged.', domain: 'devflow')
            );
        }

        return $this->redirect(admin_url('site/users/'));
    }

    /**
     * @param ServerRequest $request
     * @param string $siteId
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function siteDelete(string $siteId): ResponseInterface
    {
        if (false === current_user_can(perm: 'delete:sites')) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Access denied.', domain: 'devflow')
            );
            return $this->redirect(admin_url());
        }

        if (!is_multisite()) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Multisite is not enabled.', domain: 'devflow')
            );
            return $this->redirect(admin_url());
        }

        try {
            $connection = config()->string(key: 'database.default');

            /** @var Site $checkSite */
            $checkSite = get_site_by('id', $siteId);

            if (
                    $checkSite->key === config()->string(key: "database.connections.$connection.prefix") ||
                    $checkSite->domain === config()->string(key: 'cms.main_site_url')
            ) {
                Devflow::$PHP->flash->error(
                    message: t__(msgid: 'This action is not allowed.', domain: 'devflow')
                );
                return $this->redirect(admin_url('site/'));
            }
        } catch (
            TypeException |
            ReflectionException |
            Exception $e
        ) {
            logger(level: 'error', message: $e->getMessage());
            Devflow::$PHP->flash->error(
                t__(msgid: 'A site check exception occurred and was logged.', domain: 'devflow')
            );
        }

        try {
            $delete = cms_delete_site($siteId);

            if (is_error($delete) || is_false__($delete)) {
                Devflow::$PHP->flash->error(
                    message: Devflow::$PHP->flash->notice(num: 201)
                );
            }
        } catch (
            CommandPropertyNotFoundException |
            UnresolvableCommandHandlerException |
            TypeException |
            Exception |
            ReflectionException $e
        ) {
            logger(level: 'error', message: $e->getMessage());
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'A site deletion exception occurred and was logged.', domain: 'devflow')
            );
        }

        return $this->redirect(admin_url('site'));
    }
}
