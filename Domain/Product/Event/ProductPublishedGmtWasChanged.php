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

final class ProductPublishedGmtWasChanged extends AggregateChanged
{
    private ProductId $id;

    private DateTimeInterface $publishedGmt;

    public static function withData(
        ProductId $id,
        DateTimeInterface $publishedGmt
    ): ProductPublishedGmtWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'product_published_gmt' => (string) $publishedGmt
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'product',
            ]
        );

        $event->id = $id;
        $event->publishedGmt = $publishedGmt;

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

    public function productPublishedGmt(): DateTimeInterface
    {
        if (!isset($this->publishedGmt)) {
            $this->publishedGmt = QubusDateTimeImmutable::createFromInterface(
                new DateTime($this->payload()['product_published_gmt'])->getDateTime()
            );
        }

        return $this->publishedGmt;
    }
}
