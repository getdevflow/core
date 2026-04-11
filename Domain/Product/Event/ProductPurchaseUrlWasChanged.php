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

final class ProductPurchaseUrlWasChanged extends AggregateChanged
{
    private ProductId $id;

    private StringLiteral $purchaseUrl;

    public static function withData(
        ProductId $id,
        StringLiteral $purchaseUrl,
    ): ProductPurchaseUrlWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'product_purchase_url' => $purchaseUrl?->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'product',
            ],
        );

        $event->id = $id;
        $event->purchaseUrl = $purchaseUrl;

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

    public function productPurchaseUrl(): ?StringLiteral
    {
        if (!isset($this->purchaseUrl)) {
            $this->purchaseUrl = StringLiteral::fromNative($this->payload()['product_purchase_url']);
        }

        return $this->purchaseUrl;
    }
}
