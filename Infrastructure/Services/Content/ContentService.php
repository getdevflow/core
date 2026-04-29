<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Content;

use App\Application\Devflow;
use App\Domain\Content\Command\CreateContentCommand;
use App\Domain\Content\Command\DeleteContentCommand;
use App\Domain\Content\Command\RemoveContentParentCommand;
use App\Domain\Content\Command\RemoveFeaturedImageCommand;
use App\Domain\Content\Command\UpdateContentCommand;
use App\Domain\Content\Model\Content;
use App\Domain\Content\Validator\DestroyContentValidator;
use App\Domain\Content\Validator\FeaturedImageValidator;
use App\Domain\Content\Validator\StoreContentValidator;
use App\Domain\Content\Validator\UpdateContentValidator;
use App\Domain\Content\ValueObject\ContentId;
use App\Domain\ContentType\Model\ContentType;
use App\Infrastructure\Persistence\Cache\ContentCachePsr16;
use App\Infrastructure\Services\Content\Event\ContentCreated;
use App\Infrastructure\Services\Content\Event\ContentDeleted;
use App\Infrastructure\Services\Content\Event\ContentUpdated;
use Codefy\CommandBus\Exceptions\CommandPropertyNotFoundException;
use Codefy\CommandBus\Exceptions\UnresolvableCommandHandlerException;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
use Exception;
use PDOException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use ReflectionException;

use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\get_content_by_id;
use function App\Shared\Helpers\get_content_type_by;
use function App\Shared\Helpers\is_content_parent;
use function Codefy\Framework\Helpers\abort;
use function Codefy\Framework\Helpers\command;
use function Codefy\Framework\Helpers\logger;
use function Qubus\Security\Helpers\t__;
use function Qubus\Support\Helpers\is_false__;
use function sprintf;

final readonly class ContentService
{
    public function __construct(private EventDispatcherInterface $event)
    {
    }

    /**
     * @param string $type
     * @return ContentType
     * @throws CommandPropertyNotFoundException
     * @throws ReflectionException
     * @throws UnresolvableQueryHandlerException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     */
    public function findType(string $type): ContentType
    {
        /** @var ContentType $getContentType */
        $getContentType = get_content_type_by('slug', $type);
        if (empty($getContentType->id) || is_false__($getContentType)) {
            abort(
                code: 404,
                uri: admin_url(),
                message: t__(
                    msgid: sprintf('The content type slug `%s` does not exist.', $type),
                    domain: 'devflow'
                )
            );
        }

        return $getContentType;
    }

    /**
     * @param string $id
     * @return Content
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws \Qubus\Exception\Exception
     */
    public function findById(string $id): Content
    {
        /** @var Content $content */
        $content = get_content_by_id($id);
        if (empty($content->id) || is_false__($content)) {
            abort(
                code: 404,
                uri: admin_url(),
                message: t__(msgid: 'The content does not exist.', domain: 'devflow')
            );
        }

        return $content;
    }

    /**
     * @param StoreContentValidator $data
     * @return string
     * @throws Exception
     */
    public function createContent(StoreContentValidator $data): string
    {
        try {
            command(
                command: new CreateContentCommand(
                    data: $data->toDtoArray()
                )
            );

            /** @var Content $content */
            $content = get_content_by_id($data->toDtoArray()['id']->toNative());

            $this->event->dispatch(new ContentCreated($content->toArray()));

            Devflow::$PHP->flash->success(
                message: t__(msgid: 'Content added successfully.', domain: 'devflow'),
            );
        } catch (
            ContainerExceptionInterface |
            InvalidArgumentException |
            \Qubus\Exception\Exception |
            CommandPropertyNotFoundException |
            \ReflectionException |
            UnresolvableCommandHandlerException $e
        ) {
            logger(level: 'error', message: $e->getMessage(), context: ['ContentService' => 'createContent']);

            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Could not create content. Please try again later.', domain: 'devflow'),
            );
        }

        return $data->validated()['id'];
    }

    public function updateContent(UpdateContentValidator $data): void
    {
        try {
            command(
                command: new UpdateContentCommand(
                    data: $data->toDtoArray()
                )
            );

            /** @var Content $content */
            $content = get_content_by_id($data->toDtoArray()['id']->toNative());

            $this->event->dispatch(new ContentUpdated($content->toArray()));

            Devflow::$PHP->flash->success(Devflow::$PHP->flash->notice(num: 200));
        } catch (
            CommandPropertyNotFoundException |
            InvalidArgumentException |
            NotFoundExceptionInterface |
            ContainerExceptionInterface |
            UnresolvableCommandHandlerException |
            ReflectionException |
            \Qubus\Exception\Exception $e
        ) {
            logger('error', $e->getMessage());

            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Change exception occurred and was logged.', domain: 'devflow')
            );
        }
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws \Qubus\Exception\Exception
     */
    public function removeFeaturedImage(FeaturedImageValidator $data): void
    {
        try {
            command(
                command: new RemoveFeaturedImageCommand(
                    data: $data->toDtoArray()
                )
            );

            /** @var Content $content */
            $content = get_content_by_id($data->toDtoArray()['id']->toNative());

            $this->event->dispatch(new ContentUpdated($content->toArray()));

            Devflow::$PHP->flash->success(
                message: t__(msgid: 'Removal of featured image was successful.', domain: 'devflow')
            );
        } catch (
            CommandPropertyNotFoundException |
            UnresolvableCommandHandlerException |
            ReflectionException $e
        ) {
            logger(level: 'error', message: $e->getMessage(), context: ['ContentService' => 'removeFeaturedImage']);

            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Removal exception occurred and was logged.', domain: 'devflow')
            );
        }
    }

    /**
     * @throws UnresolvableCommandHandlerException
     * @throws ContainerExceptionInterface
     * @throws CommandPropertyNotFoundException
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     * @throws \Qubus\Exception\Exception
     * @throws ReflectionException
     * @throws TypeException
     */
    public function deleteContent(DestroyContentValidator $data): void
    {
        /** @var string $contentId */
        $contentId = $data->toDtoArray()['id']->toNative();
        /** @var Content $content */
        $content = get_content_by_id($contentId);

        if (is_content_parent($contentId)) {
            foreach (is_content_parent($contentId) as $children) {
                /** @var Content $child */
                $child = get_content_by_id($children['content_id']);
                try {
                    command(
                        new RemoveContentParentCommand([
                            'id' => ContentId::fromString($contentId),
                            'parent' => ContentId::fromString($child->id),
                        ])
                    );

                    ContentCachePsr16::clean($child->toArray());
                } catch (PDOException $ex) {
                    logger(
                        level: 'error',
                        message: sprintf(
                            'SQLSTATE[%s]: %s',
                            $ex->getCode(),
                            $ex->getMessage()
                        ),
                        context: [
                            'ContentService' => 'deleteContent'
                        ]
                    );

                    Devflow::$PHP->flash->error(
                        message: t__(msgid: 'A deletion exception occurred and was logged.', domain: 'devflow')
                    );
                }
            }
        }

        try {
            command(
                command: new DeleteContentCommand(
                    data: $data->toDtoArray()
                )
            );

            ContentCachePsr16::clean($content->toArray());

            $this->event->dispatch(new ContentDeleted($contentId));

            Devflow::$PHP->flash->success(
                message: t__(msgid: 'Removal was successful.', domain: 'devflow')
            );
        } catch (
            CommandPropertyNotFoundException |
            UnresolvableCommandHandlerException |
            ReflectionException $e
        ) {
            logger('error', $e->getMessage());

            Devflow::$PHP->flash->error(
                message: t__(msgid: 'A deletion exception occurred and was logged.', domain: 'devflow')
            );
        }
    }
}
