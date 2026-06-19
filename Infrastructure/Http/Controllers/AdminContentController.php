<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Devflow;
use App\Domain\Content\Validator\DestroyContentValidator;
use App\Domain\Content\Validator\FeaturedImageValidator;
use App\Domain\Content\Validator\StoreContentValidator;
use App\Domain\Content\Validator\UpdateContentValidator;
use App\Domain\ContentType\Model\ContentType;
use App\Infrastructure\Services\Content\ContentService;
use App\Infrastructure\Services\Content\Pipes\CastSidebarAttributeToInt;
use App\Infrastructure\Services\Content\Pipes\InitializeContentWorkflow;
use App\Infrastructure\Services\Content\Pipes\UniqueContentSlug;
use App\Shared\Pipes\CastShowInAttributesToInt;
use App\Shared\Pipes\CheckForScheduledStatus;
use App\Shared\Pipes\CompressUrls;
use App\Shared\Pipes\FormatCreatedDateTime;
use App\Shared\Pipes\FormatPublishedDateTime;
use App\Shared\Pipes\OptimizeFeaturedImage;
use Codefy\CommandBus\Exceptions\CommandPropertyNotFoundException;
use Codefy\CommandBus\Exceptions\UnresolvableCommandHandlerException;
use Codefy\Framework\Http\BaseController;
use Codefy\Framework\Pipeline\Pipeline;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Http\ServerRequest;
use ReflectionException;

use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\current_user_can;
use function App\Shared\Helpers\get_all_content_with_filters;
use function App\Shared\Helpers\get_content_type_by;
use function Codefy\Framework\Helpers\abort;
use function Codefy\Framework\Helpers\trans;
use function Codefy\Framework\Helpers\trans_html;
use function Codefy\Framework\Helpers\view;
use function Qubus\Support\Helpers\is_false__;
use function sprintf;

final class AdminContentController extends BaseController
{
    /**
     * @param ServerRequest $request
     * @param ContentService $service
     * @param string $contentTypeSlug
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Exception
     */
    public function contentCreate(
        ServerRequest $request,
        ContentService $service,
        string $contentTypeSlug
    ): ResponseInterface {
        $request = Devflow::$PHP->make(Pipeline::class)
            ->send($request)
            ->through([
                FormatPublishedDateTime::class,
                FormatCreatedDateTime::class,
                UniqueContentSlug::class,
                CheckForScheduledStatus::class,
                OptimizeFeaturedImage::class,
                CastSidebarAttributeToInt::class,
                CastShowInAttributesToInt::class,
                InitializeContentWorkflow::class,
                CompressUrls::class,
            ])
            ->thenReturn();

        $id = $service->createContent(
            StoreContentValidator::make(
                $request
            )
        );

        return $this->redirect(admin_url(path: "content-type/{$contentTypeSlug}/{$id}/"));
    }

    /**
     * @param ServerRequest $request
     * @param ContentService $service
     * @param string $contentTypeSlug
     * @return ResponseInterface
     * @throws CommandPropertyNotFoundException
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws UnresolvableQueryHandlerException
     * @throws \Exception
     */
    public function contentCreateView(
        ServerRequest $request,
        ContentService $service,
        string $contentTypeSlug
    ): ResponseInterface {
        if (false === current_user_can(perm: 'create:content')) {
            Devflow::$PHP->flash->error(
                message: trans('Access denied.')
            );
            return $this->redirect(admin_url());
        }

        $type = $service->findType($contentTypeSlug);

        return view(
            template: 'framework::backend/admin/content/create',
            data: [
                'title' => sprintf(trans_html('Create %s Content'), $type->title),
                'type' => $type,
                'request' => $request->getParsedBody(),
            ]
        );
    }

    /**
     * @param ServerRequest $request
     * @param ContentService $service
     * @param string $contentId
     * @return ResponseInterface
     * @throws Exception
     */
    public function contentChange(
        ServerRequest $request,
        ContentService $service,
        string $contentId
    ): ResponseInterface {
        $type = $request->getParsedBody()['type'];

        $request = Devflow::$PHP->make(name: Pipeline::class)
            ->send($request)
            ->through([
                FormatPublishedDateTime::class,
                UniqueContentSlug::class,
                CheckForScheduledStatus::class,
                OptimizeFeaturedImage::class,
                CastSidebarAttributeToInt::class,
                CastShowInAttributesToInt::class,
                InitializeContentWorkflow::class,
                CompressUrls::class,
            ])
            ->thenReturn();

        $service->updateContent(
            data: UpdateContentValidator::make(
                request: $request
            )
        );

        return $this->redirect(admin_url(path: "content-type/{$type}/{$contentId}/"));
    }

    /**
     * @param ContentService $service
     * @param string $contentId
     * @return ResponseInterface
     * @throws CommandPropertyNotFoundException
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws UnresolvableQueryHandlerException
     * @throws \Exception
     */
    public function contentView(ContentService $service, string $contentId): ResponseInterface
    {
        if (false === current_user_can(perm: 'update:content')) {
            Devflow::$PHP->flash->error(
                message: trans('Access denied.')
            );
            return $this->redirect(admin_url());
        }

        $content = $service->findById($contentId);

        return view(
            template: 'framework::backend/admin/content/view',
            data: [
                'title' => $content->title,
                'content' => $content,
                'type' => get_content_type_by('slug', $content->type),
            ]
        );
    }

    /**
     * @param string $contentTypeSlug
     * @return ResponseInterface
     * @throws CommandPropertyNotFoundException
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws UnresolvableQueryHandlerException
     * @throws \Exception
     */
    public function contentViewByType(string $contentTypeSlug): ResponseInterface
    {
        if (false === current_user_can(perm: 'update:content')) {
            Devflow::$PHP->flash->error(
                message: trans('Access denied.')
            );
            return $this->redirect(admin_url());
        }

        /** @var ContentType $getContentType */
        $getContentType = get_content_type_by('slug', $contentTypeSlug);
        if (empty($getContentType->id) || is_false__($getContentType)) {
            abort(
                code: 404,
                uri: admin_url(),
                message: trans('Content not found.')
            );
        }

        $content = get_all_content_with_filters($contentTypeSlug);

        return view(
            template: 'framework::backend/admin/content/content-by-type',
            data: [
                'title' => sprintf(trans_html(string: '%s Content'), $getContentType->title),
                'contentArray' => $content,
                'type' => $getContentType->slug
            ]
        );
    }

    /**
     * @param ServerRequest $request
     * @param ContentService $service
     * @param string $contentId
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     */
    public function removeFeaturedImage(
        ServerRequest $request,
        ContentService $service,
        string $contentId
    ): ResponseInterface {
        $request = $request->withParsedBody(['id' => $contentId, 'featuredImage' => '']);

        $service->removeFeaturedImage(
            data: FeaturedImageValidator::make(
                request: $request
            )
        );

        return $this->redirect($request->getHeaderLine(name: 'Referer'));
    }

    /**
     * @param ServerRequest $request
     * @param ContentService $service
     * @param string $contentId
     * @param string $contentTypeSlug
     * @return ResponseInterface
     * @throws CommandPropertyNotFoundException
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws UnresolvableCommandHandlerException
     */
    public function contentDelete(
        ServerRequest $request,
        ContentService $service,
        string $contentId,
        string $contentTypeSlug,
    ): ResponseInterface {
        $request = $request->withParsedBody(['id' => $contentId]);

        $service->deleteContent(
            data: DestroyContentValidator::make(
                request: $request
            )
        );

        return $this->redirect(admin_url("content-type/{$contentTypeSlug}/"));
    }
}
