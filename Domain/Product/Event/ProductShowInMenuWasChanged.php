<?php

declare(strict_types=1);

namespace App\Domain\Product\Event;

use App\Domain\Product\ValueObject\ProductId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;
use Qubus\ValueObjects\Number\IntegerNumber;

final class ProductShowInMenuWasChanged extends AggregateChanged
{
    private ProductId $id;

    private IntegerNumber $showInMenu;

    public static function withData(
        ProductId $id,
        IntegerNumber $showInMenu,
    ): ProductShowInMenuWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'product_show_in_menu' => $showInMenu->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'product',
            ]
        );

        $event->id = $id;
        $event->showInMenu = $showInMenu;

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
    public function productShowInMenu(): IntegerNumber
    {
        if (!isset($this->showInMenu)) {
            $this->showInMenu = IntegerNumber::fromNative($this->payload()['product_show_in_menu']);
        }

        return $this->showInMenu;
    }
}
