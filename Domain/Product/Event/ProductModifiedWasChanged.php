<?php

declare(strict_types=1);

namespace App\Domain\Product\Event;

use App\Domain\Product\ValueObject\ProductId;
use App\Shared\Services\EventDateTime;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use DateTimeInterface;
use Qubus\Exception\Data\TypeException;

final class ProductModifiedWasChanged extends AggregateChanged
{
    private ProductId $id;

    private DateTimeInterface $modified;

    public static function withData(
        ProductId $id,
        DateTimeInterface $modified,
    ): ProductModifiedWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                ...EventDateTime::payload('product_modified', $modified),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'product',
            ]
        );

        $event->id = $id;
        $event->modified = $modified;

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

    public function productModified(): DateTimeInterface
    {
        if (!isset($this->modified)) {
            $this->modified = EventDateTime::parse($this->payload(), 'product_modified');
        }

        return $this->modified;
    }
}
