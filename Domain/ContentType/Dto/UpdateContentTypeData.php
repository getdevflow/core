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

final readonly class UpdateContentTypeData implements DataTransformer
{
    private function __construct(
        public ContentTypeId $contentTypeId,
        public StringLiteral $contentTypeTitle,
        public StringLiteral $contentTypeSlug,
        public StringLiteral $contentTypeDescription,
    ) {
    }

    /**
     * @param DataValidator $data
     * @return DataTransformer
     * @throws TypeException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
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
            contentTypeId: ContentTypeId::fromString($data->string(key: 'id')),
            contentTypeTitle: new StringLiteral(value: $data->string(key: 'title')),
            contentTypeSlug: new StringLiteral(value: $contentTypeSlug),
            contentTypeDescription: new StringLiteral(value: $data->string(key: 'description', default: '')),
        );
    }
}
