<?php

declare(strict_types=1);

namespace App\Domain\ContentType\Dto;

use App\Domain\ContentType\ValueObject\ContentTypeId;
use Codefy\Framework\Dto\DataTransformer;
use Codefy\Framework\Validation\DataValidator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\ValueObjects\StringLiteral\StringLiteral;
use ReflectionException;

use function App\Shared\Helpers\cms_unique_content_type_slug;

final readonly class StoreContentTypeData implements DataTransformer
{
    private function __construct(
        public ContentTypeId $id,
        public StringLiteral $title,
        public StringLiteral $slug,
        public StringLiteral $description,
    ) {
    }

    /**
     * @param DataValidator $data
     * @return DataTransformer
     * @throws TypeException
     * @throws Exception
     * @throws ReflectionException
     */
    public static function fromValidatedData(DataValidator $data): DataTransformer
    {
        $contentTypeSlug = cms_unique_content_type_slug(
            $data->string(key: 'slug'),
            $data->string(key: 'title'),
            $data->string(key: 'id'),
        );

        return new self(
            id: ContentTypeId::fromString($data->string(key: 'id')),
            title: new StringLiteral(value: $data->string(key: 'title')),
            slug: new StringLiteral(value: $contentTypeSlug),
            description: new StringLiteral(value: $data->string(key: 'description', default: '')),
        );
    }
}
