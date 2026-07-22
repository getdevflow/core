<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\ContentType;

use App\Application\Devflow;
use App\Domain\ContentType\Command\CreateContentTypeCommand;
use App\Domain\ContentType\Command\DeleteContentTypeCommand;
use App\Domain\ContentType\Command\UpdateContentTypeCommand;
use App\Domain\ContentType\Model\ContentType;
use App\Domain\ContentType\Validator\DestroyContentTypeValidator;
use App\Domain\ContentType\Validator\StoreContentTypeValidator;
use App\Domain\ContentType\Validator\UpdateContentTypeValidator;
use App\Infrastructure\Services\ContentType\Event\ContentTypeCreated;
use App\Infrastructure\Services\ContentType\Event\ContentTypeDeleted;
use App\Infrastructure\Services\ContentType\Event\ContentTypeUpdated;
use App\Shared\Services\SimpleCacheObjectCacheFactory;
use Codefy\CommandBus\Exceptions\CommandPropertyNotFoundException;
use Codefy\CommandBus\Exceptions\UnresolvableCommandHandlerException;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Expressive\Database;
use ReflectionException;

use function App\Shared\Helpers\get_all_content_types;
use function App\Shared\Helpers\get_content_type_by;
use function Codefy\Framework\Helpers\command;
use function Codefy\Framework\Helpers\logger;
use function Codefy\Framework\Helpers\trans_html;

final readonly class ContentTypeService
{
    public function __construct(protected EventDispatcherInterface $event, protected Database $dfdb)
    {
    }

    /**
     * @return ContentType[]
     * @throws Exception
     */
    public function findContentTypes(): array
    {
        try {
            /** @var ContentType[] $contentTypes */
            $contentTypes = get_all_content_types();
        } catch (UnresolvableQueryHandlerException|ReflectionException $e) {
            Devflow::$PHP->flash->error(
                message: trans_html('Error fetching content types.')
            );
        }

        return $contentTypes;
    }

    /**
     * @param StoreContentTypeValidator $data
     * @return void
     * @throws Exception
     */
    public function createContentType(StoreContentTypeValidator $data): void
    {
        try {
            command(
                command: new CreateContentTypeCommand(
                    data: $data->toDtoArray()
                )
            );

            /** @var ContentType $contentType */
            $contentType = get_content_type_by('id', $data->toDtoArray()['id']->toNative());

            $this->event->dispatch(new ContentTypeCreated($contentType->toArray()));

            Devflow::$PHP->flash->success(Devflow::$PHP->flash->notice(num: 200));
        } catch (
            UnresolvableCommandHandlerException|
            ReflectionException|
            CommandPropertyNotFoundException|
            NotFoundExceptionInterface|
            ContainerExceptionInterface|
            InvalidArgumentException|
            UnresolvableQueryHandlerException|
            Exception $e
        ) {
            logger('error', $e->getMessage());

            Devflow::$PHP->flash->error(
                message: trans_html('Insertion exception occurred and was logged.')
            );
        }
    }

    /**
     * @param UpdateContentTypeValidator $data
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws NotFoundExceptionInterface
     */
    public function updateContentType(UpdateContentTypeValidator $data): void
    {
        try {
            command(
                command: new UpdateContentTypeCommand(
                    data: $data->toDtoArray()
                )
            );

            /** @var ContentType $contentType */
            $contentType = get_content_type_by('id', $data->toDtoArray()['id']->toNative());

            $this->event->dispatch(new ContentTypeUpdated($contentType->toArray()));
            SimpleCacheObjectCacheFactory::make($this->dfdb->prefix . 'content')->clear();

            Devflow::$PHP->flash->success(Devflow::$PHP->flash->notice(num: 200));
        } catch (
            UnresolvableCommandHandlerException|
            ReflectionException|
            CommandPropertyNotFoundException|
            UnresolvableQueryHandlerException|
            TypeException $e
        ) {
            logger('error', $e->getMessage());

            Devflow::$PHP->flash->error(
                message: trans_html('Change exception occurred and was logged.')
            );
        }
    }

    /**
     * @param DestroyContentTypeValidator $data
     * @return void
     * @throws Exception
     */
    public function deleteContentType(DestroyContentTypeValidator $data): void
    {
        /** @var string $contentTypeId */
        $contentTypeId = $data->toDtoArray()['id']->toNative();

        try {
            command(
                command: new DeleteContentTypeCommand(
                    data: $data->toDtoArray()
                )
            );

            $this->event->dispatch(new ContentTypeDeleted($contentTypeId));

            Devflow::$PHP->flash->success(Devflow::$PHP->flash->notice(num: 200));
        } catch (UnresolvableCommandHandlerException|ReflectionException|CommandPropertyNotFoundException $e) {
            logger('error', $e->getMessage());

            Devflow::$PHP->flash->error(
                message: trans_html('Delete exception occurred and was logged.')
            );
        }
    }
}
