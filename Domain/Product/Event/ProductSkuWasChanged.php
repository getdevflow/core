<?php

declare(strict_types=1);

namespace App\Domain\Product\Event;

use App\Domain\Product\ValueObject\ProductId;
use Codefy\Domain\Aggregate\AggregateId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;
use Qubus\ValueObjects\StringLiteral\StringLiteral;

final class ProductSkuWasChanged extends AggregateChanged
{
    private ProductId $id;

    private StringLiteral $sku;

    public static function withData(
        ProductId $id,
        StringLiteral $sku,
    ): ProductSkuWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'product_sku' => $sku->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'product',
            ],
        );

        $event->id = $id;
        $event->sku = $sku;

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

    public function productSku(): StringLiteral
    {
        if (!isset($this->sku)) {
            $this->sku = StringLiteral::fromNative($this->payload()['product_sku']);
        }

        return $this->sku;
    }
}
