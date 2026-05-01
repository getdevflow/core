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
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Http\ServerRequest;
use ReflectionException;
use Vihzhuo\Contracts\PageContract;
use Vihzhuo\Repositories\PageRepository;

use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\current_user_can;
use function Codefy\Framework\Helpers\config;
use function Codefy\Framework\Helpers\trans;
use function Codefy\Framework\Helpers\view;
use function phpb_trans;
use function phpb_url;

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
    public function index(ServerRequest $request): ResponseInterface
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

        if (isset($request->getQueryParams()['route']) && $request->getQueryParams()['route'] === 'page_settings') {
            if ($request->getQueryParams()['action'] === 'create') {
                return $this->handleCreate($request);
            }

            /** @var string $pageId */
            $pageId = $request->getQueryParams()['page'] ?? null;
            $pageRepository = new PageRepository;
            $page = $pageRepository->findWithId($pageId);
            if (! ($page instanceof PageContract)) {
                return $this->redirect(phpb_url('website_manager'));
            }

            if ($request->getQueryParams()['action'] === 'edit') {
                return $this->handleEdit($page, $request);
            } elseif ($request->getQueryParams()['action'] === 'destroy') {
                return $this->handleDestroy($page);
            }
        }

        return view(
            template: $this->managerIndexTemplate,
            data: [
                'title' => trans('Website Manager'),
                'pages' => $pages,
            ]
        );
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

    /**
     * @throws \Exception
     */
    private function handleCreate(ServerRequest $request): ResponseInterface
    {
        if ($request->getMethod() === 'POST') {
            $pageRepository = new PageRepository;
            $page = $pageRepository->create((array) $request->getParsedBody());
            if ($page) {
                /** @var string $message */
                $message = phpb_trans(key: 'website-manager.page-created');
                Devflow::$PHP->flash->success($message);

                return $this->redirect(phpb_url('website_manager'));
            }
        }

        return $this->renderPageSettings();
    }

    /**
     * @throws \Exception
     */
    private function handleEdit(PageContract $page, ServerRequest $request): ResponseInterface
    {
        if ($request->getMethod() === 'POST') {
            $pageRepository = new PageRepository;
            $success = $pageRepository->update($page, (array) $request->getParsedBody());
            if ($success) {
                /** @var string $message */
                $message = phpb_trans(key: 'website-manager.page-updated');
                Devflow::$PHP->flash->success($message);

                return $this->redirect(phpb_url(module: 'website_manager'));
            }
        }

        return $this->renderPageSettings($page);
    }

    private function handleDestroy(PageContract $page): ResponseInterface
    {
        $pageRepository = new PageRepository;
        $pageRepository->destroy($page->getId());
        /** @var string $message */
        $message = phpb_trans(key: 'website-manager.page-deleted');
        Devflow::$PHP->flash->success($message);

        return $this->redirect(phpb_url('website_manager'));
    }
}
