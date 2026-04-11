<?php

declare(strict_types=1);

namespace App\Domain\Content\Dto;

use App\Domain\Content\ValueObject\ContentId;
use App\Domain\User\ValueObject\UserId;
use App\Shared\Services\DateTime;
use App\Shared\ValueObject\ArrayLiteral;
use Codefy\Framework\Dto\DataTransformer;
use Codefy\Framework\Validation\DataValidator;
use DateTimeInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Support\DateTime\QubusDateTimeImmutable;
use Qubus\ValueObjects\Number\IntegerNumber;
use Qubus\ValueObjects\StringLiteral\StringLiteral;
use ReflectionException;

use function App\Shared\Helpers\get_user_timezone;

final readonly class UpdateContentData implements DataTransformer
{
    private function __construct(
        public ContentId $id,
        public StringLiteral $title,
        public StringLiteral $slug,
        public StringLiteral $body,
        public UserId $author,
        public StringLiteral $type,
        public IntegerNumber $sidebar,
        public IntegerNumber $showInMenu,
        public IntegerNumber $showInSearch,
        public StringLiteral $featuredImage,
        public ArrayLiteral $meta,
        public StringLiteral $status,
        public DateTimeInterface $published,
        public DateTimeInterface $publishedGmt,
        public DateTimeInterface $modified,
        public DateTimeInterface $modifiedGmt,
        public ?ContentId $parent = null,
    ) {
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Exception
     */
    public static function fromValidatedData(DataValidator $data): DataTransformer
    {
        $contentPublished = new DateTime(
            time: $data->string(key: 'published')
        )->getDateTime();

        $contentPublishedGmt = new DateTime(
            time: $data->string(key: 'publishedGmt'),
        )->getDateTime();

        $contentModified = new DateTime(QubusDateTimeImmutable::now(get_user_timezone())->toDateTimeString())
            ->getDateTime();
        $contentModifiedGmt = new DateTime(QubusDateTimeImmutable::now('GMT')->toDateTimeString())->getDateTime();

        return new self(
            id: ContentId::fromString($data->string(key: 'id')),
            title: new StringLiteral(value: $data->string(key: 'title')),
            slug: new StringLiteral(value: $data->string(key: 'slug')),
            body: new StringLiteral(value: $data->string(key: 'body', default: '')),
            author: UserId::fromString(userId: $data->string(key: 'author')),
            type: new StringLiteral(value: $data->string(key: 'type')),
            sidebar: new IntegerNumber(value: $data->value(value: 'sidebar') ?? 0),
            showInMenu: new IntegerNumber(value: $data->value(value: 'showInMenu') ?? 0),
            showInSearch: new IntegerNumber(value: $data->value(value: 'showInSearch') ?? 0),
            featuredImage: new StringLiteral(value: $data->string(key: 'featuredImage')),
            meta: new ArrayLiteral(data: $data->array(key: 'content_field', default: [])),
            status: new StringLiteral(value: $data->string(key: 'status')),
            published: $contentPublished,
            publishedGmt: $contentPublishedGmt,
            modified: $contentModified,
            modifiedGmt: $contentModifiedGmt,
            parent: 'NULL' !== $data->string(key: 'parent') ? ContentId::fromString(contentId: $data->string(key: 'parent')) : null,
        );
    }
}
