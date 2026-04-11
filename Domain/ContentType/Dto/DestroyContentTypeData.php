<?php

declare(strict_types=1);

namespace App\Domain\ContentType\Dto;

use App\Domain\ContentType\ValueObject\ContentTypeId;
use Codefy\Framework\Dto\DataTransformer;
use Codefy\Framework\Validation\DataValidator;
use Qubus\Exception\Data\TypeException;

final readonly class DestroyContentTypeData implements DataTransformer
{
    private function __construct(
        public ContentTypeId $contentTypeId,
    ) {
    }

    /**
     * @param DataValidator $data
     * @return DataTransformer
     * @throws TypeException
     */
    public static function fromValidatedData(DataValidator $data): DataTransformer
    {
        return new self(
            contentTypeId: ContentTypeId::fromString($data->string(key: 'id'))
        );
    }
}
