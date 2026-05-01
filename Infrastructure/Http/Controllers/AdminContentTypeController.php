<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Devflow;
use App\Domain\ContentType\Model\ContentType;
use App\Domain\ContentType\Validator\DestroyContentTypeValidator;
use App\Domain\ContentType\Validator\StoreContentTypeValidator;
use App\Domain\ContentType\Validator\UpdateContentTypeValidator;
use App\Infrastructure\Services\ContentType\ContentTypeService;
use Codefy\CommandBus\Exceptions\CommandPropertyNotFoundException;
use Codefy\Framework\Http\BaseController;
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
use function App\Shared\Helpers\get_content_type_by;
use function Codefy\Framework\Helpers\abort;
use function Codefy\Framework\Helpers\trans;
use function Codefy\Framework\Helpers\view;
use function Qubus\Support\Helpers\is_false__;

final class AdminContentTypeController extends BaseController
{
    /**
     * @param ServerRequest $request
     * @param ContentTypeService $service
     * @return ResponseInterface
     */
    public function contentTypeCreate(ServerRequest $request, ContentTypeService $service): ResponseInterface
    {
        $service->createContentType(
            data: StoreContentTypeValidator::make(
                request: $request
            )
        );

        return $this->redirect($request->getHeaderLine(name: 'Referer'));
    }

    /**
     * @param ServerRequest $request
     * @param ContentTypeService $service
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Exception
     */
    public function contentTypes(ServerRequest $request, ContentTypeService $service): ResponseInterface
    {
        if (false === current_user_can(perm: 'manage:content')) {
            Devflow::$PHP->flash->error(
                message: trans('Access denied.')
            );
            return $this->redirect(admin_url());
        }

        $contentTypes = $service->findContentTypes();

        return view(
            template: 'framework::backend/admin/content-type/content-type',
            data: [
                'title' => trans('Content Types'),
                'types' => $contentTypes,
                'request' => $request->getParsedBody(),
            ]
        );
    }

    /**
     * @param ServerRequest $request
     * @param ContentTypeService $service
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function contentTypeChange(ServerRequest $request, ContentTypeService $service): ResponseInterface
    {
        $service->updateContentType(
            data: UpdateContentTypeValidator::make(
                request: $request
            )
        );

        return $this->redirect($request->getHeaderLine(name: 'Referer'));
    }

    /**
     * @param string $contentTypeId
     * @return string|ResponseInterface
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
    public function contentTypeView(string $contentTypeId): string|ResponseInterface
    {
        if (false === current_user_can(perm: 'update:content')) {
            Devflow::$PHP->flash->error(
                message: trans('Access denied.')
            );
            return $this->redirect(admin_url());
        }

        /** @var ContentType $contentType */
        $contentType = get_content_type_by('id', $contentTypeId);

        if (empty($contentType->id)) {
            abort(
                code: 404,
                uri: admin_url('content-type'),
                message: trans('The content type does not exist.')
            );
        }

        if (is_false__($contentType)) {
            abort(
                code: 404,
                uri: admin_url('content-type'),
                message: trans('The content type does not exist.')
            );
        }

        return view(
            template: 'framework::backend/admin/content-type/update-content-type',
            data: [
                'title' => $contentType->title,
                'type' => $contentType,
            ]
        );
    }

    /**
     * @param ServerRequest $request
     * @param ContentTypeService $service
     * @param string $contentTypeId
     * @return ResponseInterface
     * @throws Exception
     */
    public function contentTypeDelete(ServerRequest $request, ContentTypeService $service, string $contentTypeId): ResponseInterface
    {
        $request = $request->withParsedBody(['id' => $contentTypeId]);

        $service->deleteContentType(
            data: DestroyContentTypeValidator::make(
                request: $request
            )
        );

        return $this->redirect(admin_url('content-type'));
    }
}
