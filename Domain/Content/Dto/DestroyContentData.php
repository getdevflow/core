<?php

declare(strict_types=1);

namespace App\Domain\Content\Dto;

use App\Domain\Content\ValueObject\ContentId;
use Codefy\Framework\Dto\DataTransformer;
use Codefy\Framework\Validation\DataValidator;
use Qubus\Exception\Data\TypeException;

final readonly class DestroyContentData implements DataTransformer
{
    private function __construct(
        public ContentId $id,
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
            id: ContentId::fromString($data->string(key: 'id'))
        );
    }
}
