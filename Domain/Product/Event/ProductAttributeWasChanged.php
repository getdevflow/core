<?php

declare(strict_types=1);

namespace App\Domain\Product\Event;

use App\Domain\Product\ValueObject\ProductId;
use App\Shared\ValueObject\ArrayLiteral;
use Codefy\Domain\Aggregate\AggregateId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;

class ProductAttributeWasChanged extends AggregateChanged
{
    private ProductId $id;

    private ArrayLiteral $attribute;

    public static function withData(
        ProductId $id,
        ?ArrayLiteral $attribute = null
    ): ProductAttributeWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'product_attribute' => $attribute->toNative()
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'product',
            ]
        );

        $event->id = $id;
        $event->attribute = $attribute;

        return $event;
    }

    /**
     * @throws TypeException
     */
    public function productId(): ProductId|AggregateId
    {
        if (!isset($this->id)) {
            $this->id = ProductId::fromString(productId: $this->aggregateId()->__toString());
        }

        return $this->id;
    }

    public function productAttribute(): ArrayLiteral
    {
        if (!isset($this->attribute)) {
            $this->attribute = ArrayLiteral::fromNative($this->payload()['product_attribute']);
        }

        return $this->attribute;
    }
}
