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
use Qubus\EventDispatcher\ActionFilter\Action;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Http\ServerRequest;
use ReflectionException;
use Vihzhuo\Contracts\PageContract;
use Vihzhuo\Repositories\PageRepository;

use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\current_user_can;
use function App\Shared\Helpers\update_page_attribute;
use function Codefy\Framework\Helpers\config;
use function Codefy\Framework\Helpers\trans;
use function Codefy\Framework\Helpers\view;
use function phpb_trans;
use function sprintf;

final class WebsiteManagerController extends BaseController
{
    private string $managerIndexTemplate = 'framework::backend/admin/manager/index';
    private string $pageSettingsTemplate = 'framework::backend/admin/manager/page-settings';

    /**
     * @param ServerRequest $request
     * @return ResponseInterface
     * @throws TypeException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ReflectionException
     * @throws \Exception
     */
    public function index(): ResponseInterface
    {
        if (false === current_user_can(perm: 'vihzhuo:manage')) {
            Devflow::$PHP->flash->error(
                message: trans('Access denied.')
            );

            return $this->redirect(admin_url());
        }

        $this->vihzhuoInstance();

        $pageRepository = new PageRepository();
        $pages = $pageRepository->getAll();

        return view(
            template: $this->managerIndexTemplate,
            data: [
                'title' => trans('Website Manager'),
                'pages' => $pages,
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
     * @throws \Exception
     */
    public function create(ServerRequest $request): ResponseInterface
    {
        if (false === current_user_can(perm: 'vihzhuo:manage')) {
            Devflow::$PHP->flash->error(message: trans('Access denied.'));
            return $this->redirect(admin_url());
        }

        $this->vihzhuoInstance();

        if ($request->getMethod() === 'POST') {
            $body = (array) $request->getParsedBody();

            $pageRepository = new PageRepository();
            $page = $pageRepository->create($body);

            if ($page) {
                Devflow::$PHP->flash->success(
                    phpb_trans(key: 'website-manager.page-created')
                );

                return $this->redirect(admin_url('manager/'));
            }
        }

        return $this->renderPageSettings();
    }

    /**
     * @param ServerRequest $request
     * @param int $pageId
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Exception
     */
    public function edit(ServerRequest $request, int $pageId): ResponseInterface
    {
        if (false === current_user_can(perm: 'vihzhuo:manage')) {
            Devflow::$PHP->flash->error(message: trans('Access denied.'));
            return $this->redirect(admin_url());
        }

        $this->vihzhuoInstance();

        $pageRepository = new PageRepository();
        $page = $pageRepository->findWithId((string) $pageId);

        if ($page === null) {
            return $this->redirect(admin_url('manager/'));
        }

        if ($request->getMethod() === 'POST') {
            $body = (array) $request->getParsedBody();

            $success = $pageRepository->update($page, $body);

            if ($success) {
                if (isset($body['page_field']) && is_array($body['page_field'])) {
                    foreach ($body['page_field'] as $key => $value) {
                        update_page_attribute($page->getId(), (string) $key, $value);
                    }
                }

                Action::getInstance()->doAction('update_page', $page);

                Devflow::$PHP->flash->success(
                    phpb_trans(key: 'website-manager.page-updated')
                );

                return $this->redirect(admin_url(sprintf('manager/%s/', $page->getId())));
            }
        }

        return $this->renderPageSettings($page);
    }

    /**
     * @param ServerRequest $request
     * @param int $pageId
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function destroy(ServerRequest $request, int $pageId): ResponseInterface
    {
        if (false === current_user_can(perm: 'vihzhuo:manage')) {
            Devflow::$PHP->flash->error(message: trans('Access denied.'));
            return $this->redirect(admin_url());
        }

        $this->vihzhuoInstance();

        $pageRepository = new PageRepository();
        $page = $pageRepository->findWithId((string) $pageId);

        if ($page === null) {
            return $this->redirect(admin_url('manager/'));
        }

        $pageRepository->destroy($page->getId());

        Action::getInstance()->doAction('deleted_page', $page);

        Devflow::$PHP->flash->success(
            phpb_trans(key: 'website-manager.page-deleted')
        );

        return $this->redirect(admin_url('manager/'));
    }

    /**
     * @throws TypeException
     */
    private function vihzhuoInstance(): void
    {
        new DevflowPageBuilder(config()->array(key: 'vihzhuo'));
    }

    /**
     * @throws \Exception
     */
    private function renderPageSettings(?PageContract $page = null): ResponseInterface
    {
        $action = isset($page) ? 'edit' : 'create';
        $theme = phpb_instance(name: 'theme', params: [
            phpb_config(key: 'theme'),
            phpb_config(key: 'theme.active_theme')
        ]);

        return view(
            template: $this->pageSettingsTemplate,
            data: [
                'title' => trans('Website Manager'),
                'page' => $page,
                'action' => $action,
                'theme' => $theme,
            ]
        );
    }
}
