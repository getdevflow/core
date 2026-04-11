<?php

declare(strict_types=1);

namespace App\Domain\Product\Dto;

use App\Domain\Product\ValueObject\ProductId;
use Codefy\Framework\Dto\DataTransformer;
use Codefy\Framework\Validation\DataValidator;
use Qubus\Exception\Data\TypeException;

final readonly class DestroyProductData implements DataTransformer
{
    private function __construct(
        public ProductId $id,
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
            id: ProductId::fromString($data->string(key: 'id'))
        );
    }
}
