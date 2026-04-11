<?php

declare(strict_types=1);

namespace App\Domain\Product\Event;

use App\Domain\Product\ValueObject\ProductId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;
use Qubus\ValueObjects\StringLiteral\StringLiteral;

final class ProductFeaturedImageWasChanged extends AggregateChanged
{
    private ProductId $id;

    private StringLiteral $featuredImage;

    public static function withData(
        ProductId $id,
        StringLiteral $featuredImage,
    ): ProductFeaturedImageWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'product_featured_image' => $featuredImage->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'product',
            ]
        );

        $event->id = $id;
        $event->featuredImage = $featuredImage;

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

    public function productFeaturedImage(): StringLiteral
    {
        if (!isset($this->featuredImage)) {
            $this->featuredImage = StringLiteral::fromNative($this->payload()['product_featured_image']);
        }

        return $this->featuredImage;
    }
}
