<?php

declare(strict_types=1);

namespace App\Domain\Product\Event;

use App\Domain\Product\ValueObject\ProductId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;
use Qubus\ValueObjects\StringLiteral\StringLiteral;

final class ProductStatusWasChanged extends AggregateChanged
{
    private ProductId $id;

    private StringLiteral $status;

    public static function withData(
        ProductId $id,
        StringLiteral $status,
    ): ProductStatusWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'product_status' => $status->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'product',
            ]
        );

        $event->id = $id;
        $event->status = $status;

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

    public function productStatus(): StringLiteral
    {
        if (!isset($this->status)) {
            $this->status = StringLiteral::fromNative($this->payload()['product_status']);
        }

        return $this->status;
    }
}
