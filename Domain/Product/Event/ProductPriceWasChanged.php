<?php

declare(strict_types=1);

namespace App\Domain\Product\Event;

use App\Domain\Product\ValueObject\ProductId;
use Codefy\Domain\Aggregate\AggregateId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;
use Qubus\ValueObjects\Money\Money;

final class ProductPriceWasChanged extends AggregateChanged
{
    private ProductId $id;

    private Money $price;

    /**
     * @throws TypeException
     */
    public static function withData(
        ProductId $id,
        Money $price,
    ): ProductPriceWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'product_price' => $price->getAmount()->toNative(),
                'product_currency' => $price->getCurrency()->getCode()->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'product',
            ],
        );

        $event->id = $id;
        $event->price = $price;

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

    /**
     * @throws TypeException
     */
    public function productPrice(): Money
    {
        if (!isset($this->price)) {
            $this->price = Money::fromNative(
                $this->payload()['product_price'],
                $this->payload()['product_currency']
            );
        }

        return $this->price;
    }
}
