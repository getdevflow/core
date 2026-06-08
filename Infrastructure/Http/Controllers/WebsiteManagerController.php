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

use function App\Shared\Helpers\add_page_attribute;
use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\current_user_can;
use function App\Shared\Helpers\update_page_attribute;
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
            $pageRepository = new PageRepository();
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
     * @param ServerRequest $request
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Exception
     */
    private function handleCreate(ServerRequest $request): ResponseInterface
    {
        if ($request->getMethod() === 'POST') {
            $body = (array) $request->getParsedBody();
            $pageRepository = new PageRepository();
            $page = $pageRepository->create($body);
            if ($page) {
                if (isset($body['page_field'])) {
                    foreach ($body['page_field'] as $key => $value) {
                        add_page_attribute($page->getId(), $key, $value);
                    }
                }

                Action::getInstance()->doAction('create_page', $page);

                /** @var string $message */
                $message = phpb_trans(key: 'website-manager.page-created');
                Devflow::$PHP->flash->success($message);

                return $this->redirect(phpb_url('website_manager'));
            }
        }

        return $this->renderPageSettings();
    }

    /**
     * @param PageContract $page
     * @param ServerRequest $request
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Exception
     */
    private function handleEdit(PageContract $page, ServerRequest $request): ResponseInterface
    {
        if ($request->getMethod() === 'POST') {
            $body = (array) $request->getParsedBody();
            $pageRepository = new PageRepository();
            $success = $pageRepository->update($page, $body);
            if ($success) {
                if (isset($body['page_field'])) {
                    foreach ($body['page_field'] as $key => $value) {
                        update_page_attribute($page->getId(), $key, $value);
                    }
                }

                Action::getInstance()->doAction('update_page', $page);

                /** @var string $message */
                $message = phpb_trans(key: 'website-manager.page-updated');
                Devflow::$PHP->flash->success($message);

                return $this->redirect(phpb_url(module: 'website_manager'));
            }
        }

        return $this->renderPageSettings($page);
    }

    /**
     * @param PageContract $page
     * @return ResponseInterface
     * @throws Exception
     * @throws ReflectionException
     */
    private function handleDestroy(PageContract $page): ResponseInterface
    {
        $pageRepository = new PageRepository();
        $pageRepository->destroy($page->getId());
        /** @var string $message */
        $message = phpb_trans(key: 'website-manager.page-deleted');
        Devflow::$PHP->flash->success($message);

        Action::getInstance()->doAction('deleted_page', $page);

        return $this->redirect(phpb_url('website_manager'));
    }
}
