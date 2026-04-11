<?php

declare(strict_types=1);

namespace App\Domain\Product\Dto;

use App\Domain\Product\ValueObject\ProductId;
use Codefy\Framework\Dto\DataTransformer;
use Codefy\Framework\Validation\DataValidator;
use Qubus\Exception\Data\TypeException;
use Qubus\ValueObjects\StringLiteral\StringLiteral;

final readonly class FeaturedImageData implements DataTransformer
{
    private function __construct(
        public ProductId $id,
        public StringLiteral $featuredImage,
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
            id: ProductId::fromString($data->string(key: 'id')),
            featuredImage: new StringLiteral(value: $data->string(key: 'featuredImage')),
        );
    }
}
