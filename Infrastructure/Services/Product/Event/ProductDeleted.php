<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Product\Event;

use Qubus\Support\DateTime\QubusDateTimeImmutable;

final readonly class ProductDeleted
{
    /**
     * @param string $productId
     * @param string|null $actorId
     * @param array $context
     * @param QubusDateTimeImmutable $occurredAt
     */
    public function __construct(
        public string $productId,
        public string|null $actorId = null,
        public array $context = [],
        public QubusDateTimeImmutable $occurredAt = new QubusDateTimeImmutable(time: 'now'),
    ) {
    }
}
