<?php

declare(strict_types=1);

namespace App\Domain\Product\Event;

use App\Domain\Product\ValueObject\ProductId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;
use Qubus\ValueObjects\Number\IntegerNumber;

final class ProductShowInSearchWasChanged extends AggregateChanged
{
    private ProductId $id;

    private IntegerNumber $showInSearch;

    public static function withData(
        ProductId $id,
        IntegerNumber $showInSearch,
    ): ProductShowInSearchWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'product_show_in_search' => $showInSearch->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'product',
            ]
        );

        $event->id = $id;
        $event->showInSearch = $showInSearch;

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

    /**
     * @throws TypeException
     */
    public function productShowInSearch(): IntegerNumber
    {
        if (!isset($this->showInSearch)) {
            $this->showInSearch = IntegerNumber::fromNative($this->payload()['product_show_in_search']);
        }

        return $this->showInSearch;
    }
}
