<?php

declare(strict_types=1);

namespace App\Domain\Product\Event;

use App\Domain\Product\ValueObject\ProductId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;

final class ProductWasDeleted extends AggregateChanged
{
    private ProductId $id;

    public static function withData(
        ProductId $id,
    ): ProductWasDeleted|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'product',
            ]
        );

        $event->id = $id;

        return $event;
    }

    /**
     * @throws TypeException
     */
    public function productId(): ProductId
    {
        if (!isset($this->id)) {
            $this->id = ProductId::fromString($this->aggregateId()->__toString());
        }

        return $this->id;
    }
}
