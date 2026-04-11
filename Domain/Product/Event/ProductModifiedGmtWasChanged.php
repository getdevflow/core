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

final class ProductModifiedGmtWasChanged extends AggregateChanged
{
    private ProductId $id;

    private DateTimeInterface $modifiedGmt;

    public static function withData(
        ProductId $id,
        DateTimeInterface $modifiedGmt
    ): ProductModifiedGmtWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'product_modified_gmt' => (string) $modifiedGmt
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'product',
            ]
        );

        $event->id = $id;
        $event->modifiedGmt = $modifiedGmt;

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

    public function productModifiedGmt(): DateTimeInterface
    {
        if (!isset($this->modifiedGmt)) {
            $this->modifiedGmt = QubusDateTimeImmutable::createFromInterface(
                new DateTime($this->payload()['product_modified_gmt'])->getDateTime()
            );
        }

        return $this->modifiedGmt;
    }
}
