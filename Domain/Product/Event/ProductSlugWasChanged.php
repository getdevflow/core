<?php

declare(strict_types=1);

namespace App\Domain\Product\Event;

use App\Domain\Product\ValueObject\ProductId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;
use Qubus\ValueObjects\StringLiteral\StringLiteral;

final class ProductSlugWasChanged extends AggregateChanged
{
    private ProductId $id;

    private StringLiteral $slug;

    public static function withData(
        ProductId $id,
        StringLiteral $slug,
    ): ProductSlugWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'product_slug' => $slug->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'product',
            ]
        );

        $event->id = $id;
        $event->slug = $slug;

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

    public function productSlug(): StringLiteral
    {
        if (!isset($this->slug)) {
            $this->slug = StringLiteral::fromNative($this->payload()['product_slug']);
        }

        return $this->slug;
    }
}
