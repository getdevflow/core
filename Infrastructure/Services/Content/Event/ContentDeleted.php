<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Content\Event;

use Qubus\Support\DateTime\QubusDateTimeImmutable;

final readonly class ContentDeleted
{
    /**
     * @param string $contentId
     * @param string|null $actorId
     * @param array $context
     * @param QubusDateTimeImmutable $occurredAt
     */
    public function __construct(
        public string $contentId,
        public string|null $actorId = null,
        public array $context = [],
        public QubusDateTimeImmutable $occurredAt = new QubusDateTimeImmutable(time: 'now'),
    ) {
    }
}
