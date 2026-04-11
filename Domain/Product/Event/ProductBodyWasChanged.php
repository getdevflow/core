<?php

declare(strict_types=1);

namespace App\Domain\Product\Event;

use App\Domain\Product\ValueObject\ProductId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;
use Qubus\ValueObjects\StringLiteral\StringLiteral;

final class ProductBodyWasChanged extends AggregateChanged
{
    private ProductId $id;

    private StringLiteral $body;

    public static function withData(
        ProductId $id,
        StringLiteral $body,
    ): ProductBodyWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'product_body' => $body->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'product',
            ]
        );

        $event->id = $id;
        $event->body = $body;

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

    public function productBody(): StringLiteral
    {
        if (!isset($this->body)) {
            $this->body = StringLiteral::fromNative($this->payload()['product_body']);
        }

        return $this->body;
    }
}
