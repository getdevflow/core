<?php

declare(strict_types=1);

namespace App\Domain\Product\Event;

use App\Domain\Product\ValueObject\ProductId;
use App\Shared\Services\DateTime;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use DateTimeInterface;
use Qubus\Exception\Data\TypeException;
use Qubus\Support\DateTime\QubusDateTimeImmutable;

final class ProductPublishedWasChanged extends AggregateChanged
{
    private ProductId $id;

    private DateTimeInterface $published;

    public static function withData(
        ProductId $id,
        DateTimeInterface $published,
    ): ProductPublishedWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'product_published' => (string) $published,
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'product',
            ]
        );

        $event->id = $id;
        $event->published = $published;

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

    public function productPublished(): DateTimeInterface
    {
        if (!isset($this->published)) {
            $this->published = QubusDateTimeImmutable::createFromInterface(
                new DateTime($this->payload()['product_published'])->getDateTime()
            );
        }

        return $this->published;
    }
}
