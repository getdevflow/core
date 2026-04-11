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
use Qubus\Exception\Data\TypeException;
use Qubus\ValueObjects\Number\IntegerNumber;
use Qubus\ValueObjects\StringLiteral\StringLiteral;

final readonly class StoreContentData implements DataTransformer
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
        public DateTimeInterface $created,
        public DateTimeInterface $createdGmt,
        public DateTimeInterface $published,
        public DateTimeInterface $publishedGmt,
        public ?ContentId $parent = null,
    ) {
    }

    /**
     * @param DataValidator $data
     * @return DataTransformer
     * @throws TypeException
     */
    public static function fromValidatedData(DataValidator $data): DataTransformer
    {
        $contentPublished = new DateTime(
            time: $data->string(key: 'published')
        )->getDateTime();

        $contentPublishedGmt = new DateTime(
            time: $data->string(key: 'publishedGmt'),
        )->getDateTime();

        $contentCreated = new DateTime(
            time: $data->string(key: 'created')
        )->getDateTime();

        $contentCreatedGmt = new DateTime(
            time: $data->string(key: 'createdGmt'),
        )->getDateTime();

        return new self(
            id: ContentId::fromString($data->string(key: 'id')),
            title: new StringLiteral(value: $data->string(key: 'title')),
            slug: new StringLiteral(value: $data->string(key: 'slug')),
            body: new StringLiteral(value: $data->string(key: 'body', default: '')),
            author: UserId::fromString(userId: $data->string(key: 'author')),
            type: new StringLiteral(value: $data->string(key: 'type')),
            sidebar: new IntegerNumber(value: $data->integer(key: 'sidebar')),
            showInMenu: new IntegerNumber(value: $data->integer(key: 'showInMenu')),
            showInSearch: new IntegerNumber(value: $data->integer(key: 'showInSearch')),
            featuredImage: new StringLiteral(value: $data->string(key: 'featuredImage')),
            meta: new ArrayLiteral(data: $data->array(key: 'content_field', default: [])),
            status: new StringLiteral(value: $data->string(key: 'status')),
            created: $contentCreated,
            createdGmt: $contentCreatedGmt,
            published: $contentPublished,
            publishedGmt: $contentPublishedGmt,
            parent: 'NULL' !== $data->string(key: 'parent') ? ContentId::fromString(contentId: $data->string(key: 'parent')) : null,
        );
    }
}
