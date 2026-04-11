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

class ProductMetaWasChanged extends AggregateChanged
{
    private ProductId $id;

    private ArrayLiteral $meta;

    public static function withData(
        ProductId $id,
        ?ArrayLiteral $meta = null
    ): ProductMetaWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'meta' => $meta->toNative()
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'product',
            ]
        );

        $event->id = $id;
        $event->meta = $meta;

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

    public function productMeta(): ArrayLiteral
    {
        if (!isset($this->meta)) {
            $this->meta = ArrayLiteral::fromNative($this->payload()['meta']);
        }

        return $this->meta;
    }
}
