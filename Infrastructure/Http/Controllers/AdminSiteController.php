<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Devflow;
use App\Domain\Site\Model\Site;
use App\Infrastructure\Services\AttributesFactory;
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

use function App\Shared\Helpers\add_user_to_site;
use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\cms_delete_site;
use function App\Shared\Helpers\cms_delete_site_user;
use function App\Shared\Helpers\cms_insert_site;
use function App\Shared\Helpers\cms_update_site;
use function App\Shared\Helpers\current_user_can;
use function App\Shared\Helpers\get_all_sites;
use function App\Shared\Helpers\get_all_users;
use function App\Shared\Helpers\get_current_site_id;
use function App\Shared\Helpers\get_site_by;
use function App\Shared\Helpers\is_main_site;
use function App\Shared\Helpers\is_multisite;
use function App\Shared\Helpers\sort_list;
use function Codefy\Framework\Helpers\config;
use function Codefy\Framework\Helpers\logger;
use function Codefy\Framework\Helpers\trans;
use function Codefy\Framework\Helpers\view;
use function Qubus\Error\Helpers\is_error;
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
                message: trans('Access denied.')
            );
            return $this->redirect(admin_url());
        }

        if (!is_multisite()) {
            Devflow::$PHP->flash->error(
                message: trans('Multisite is not enabled.')
            );
            return $this->redirect(admin_url());
        }

        try {
            $id = cms_insert_site($request);
            if (is_error($id)) {
                Devflow::$PHP->flash->error(
                    message: trans('Insertion error occurred.')
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
                message: trans('Insertion exception occurred and was logged.')
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
                message: trans('Access denied.')
            );
            return $this->redirect(admin_url());
        }

        if (!is_multisite()) {
            Devflow::$PHP->flash->error(
                message: trans('Multisite is not enabled.')
            );
            return $this->redirect(admin_url());
        }

        try {
            $connection = config()->string(key: 'database.default');

            $sites = get_all_sites();

            return view(
                template: 'framework::backend/admin/site/index',
                data: [
                    'title' => trans('Sites'),
                    'sites' => $sites,
                    'request' => $request,
                    'connection' => $connection,
                ]
            );
        } catch (UnresolvableQueryHandlerException | ReflectionException $e) {
            logger(level: 'error', message: $e->getMessage());
            Devflow::$PHP->flash->error(
                message: trans('Exception occurred and was logged.')
            );
        }

        return JsonResponseFactory::create(data: trans('Content types error.'), status: 404);
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
                message: trans('Access denied.')
            );
            return $this->redirect(admin_url());
        }

        if (!is_multisite()) {
            Devflow::$PHP->flash->error(
                message: trans('Multisite is not enabled.')
            );
            return $this->redirect(admin_url());
        }

        $dataArrayMerge = array_merge(['id' => $siteId], $request->getParsedBody());

        try {
            $id = cms_update_site($dataArrayMerge);
            if (is_error($id)) {
                Devflow::$PHP->flash->error(
                    message: trans($id->getMessage())
                );
                return $this->redirect($request->getHeaderLine('Referer'));
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
                message: trans('Change exception occurred and was logged.')
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
                message: trans('Access denied.')
            );
            return $this->redirect(admin_url());
        }

        if (!is_multisite()) {
            Devflow::$PHP->flash->error(
                message: trans('Multisite is not enabled.')
            );
            return $this->redirect(admin_url());
        }

        try {
            /** @var Site $site */
            $site = get_site_by(field: 'id', value: $siteId);

            if (is_false__($site)) {
                return JsonResponseFactory::create(
                    data: trans('The site does not exist.'),
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
            data: trans('The site does not exist.'),
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
                message: trans('Access denied.')
            );
            return $this->redirect(admin_url());
        }

        if (!is_multisite()) {
            Devflow::$PHP->flash->error(
                message: trans('Multisite is not enabled.')
            );
            return $this->redirect(admin_url());
        }

        if(!is_main_site(get_current_site_id())) {
            Devflow::$PHP->flash->error(
                message: trans('The action is not allowed.')
            );
            return $this->redirect(admin_url());
        }
        
        $results = get_all_users();
        $users = sort_list($results, 'lname', 'ASC', true);

        return view(
            template: 'framework::backend/admin/site/users',
            data: [
                'title' => trans('Manage System Users'),
                'users' => $users,
            ]
        );
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
     */
    public function siteUserAssign(ServerRequest $request): ResponseInterface
    {
        if (false === current_user_can(perm: 'manage:sites')) {
            Devflow::$PHP->flash->error(
                message: trans('Access denied.')
            );
            return $this->redirect(admin_url());
        }
        if (!is_multisite()) {
            Devflow::$PHP->flash->error(
                message: trans('Multisite is not enabled.')
            );
            return $this->redirect(admin_url());
        }
        if('null' === $request->get('site_id')) {
            Devflow::$PHP->flash->error(
                message: trans('You cannot submit an empty site id.')
            );
            return $this->redirect($request->getHeaderLine('Referer'));
        }
        if('null' === $request->get('user_role')) {
            Devflow::$PHP->flash->error(
                message: trans('You cannot submit an empty user role.')
            );
            return $this->redirect($request->getHeaderLine('Referer'));
        }

        AttributesFactory::user()->createIfMissing($request->get('site_id'), $request->get('user_id'));

        $addUserToSite = add_user_to_site($request->get('user_id'), $request->get('site_id'), $request->get('user_role'));
        if(is_false__($addUserToSite)) {
            Devflow::$PHP->flash->error(
                message: trans('An error occurred.')
            );
        } else {
            Devflow::$PHP->flash->success(Devflow::$PHP->flash->notice(num: 200));
        }

        return $this->redirect($request->getHeaderLine('Referer'));

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
                message: trans('Access denied.')
            );
            return $this->redirect(admin_url('site/users/'));
        }

        if (!is_multisite()) {
            Devflow::$PHP->flash->error(
                message: trans('Multisite is not enabled.')
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
                trans('Delete exception occurred and was logged.')
            );
        }

        return $this->redirect(admin_url('site/users/'));
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
     */
    public function siteDelete(string $siteId): ResponseInterface
    {
        if (false === current_user_can(perm: 'delete:sites')) {
            Devflow::$PHP->flash->error(
                message: trans('Access denied.')
            );
            return $this->redirect(admin_url());
        }

        if (!is_multisite()) {
            Devflow::$PHP->flash->error(
                message: trans('Multisite is not enabled.')
            );
            return $this->redirect(admin_url());
        }

        try {
            if (is_main_site($siteId)) {
                Devflow::$PHP->flash->error(
                    message: trans('This action is not allowed.')
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
                trans('A site check exception occurred and was logged.')
            );
        }

        try {
            $delete = cms_delete_site($siteId);

            if (is_error($delete) || is_false__($delete)) {
                Devflow::$PHP->flash->error(
                    message: Devflow::$PHP->flash->notice(num: 201)
                );
            } else {
                Devflow::$PHP->flash->success(Devflow::$PHP->flash->notice(num: 200));
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
                message: trans('A site deletion exception occurred and was logged.')
            );
        }

        return $this->redirect(admin_url('site'));
    }
}
