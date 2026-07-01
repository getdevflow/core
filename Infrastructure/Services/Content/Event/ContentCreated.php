<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Content\Event;

use Qubus\Support\DateTime\QubusDateTimeImmutable;

final readonly class ContentCreated
{
    /**
     * @param array{
     *     'id': string,
     *     'title': string,
     *     'slug': string,
     *     'body': string,
     *     'author': string,
     *     'type': string,
     *     'parent': string|null,
     *     'sidebar': int,
     *     'showInMenu': int,
     *     'showInSearch': int,
     *     'featuredImage': string,
     *     'status': string,
     *     'created': string,
     *     'createdGmt': string,
     *     'published': string,
     *     'publishedGmt': string,
     * } $content
     */
    public function __construct(
        public array $content,
        public string|null $actorId = null,
        public array $context = [],
        public QubusDateTimeImmutable $occurredAt = new QubusDateTimeImmutable(time: 'now'),
    ) {
    }
}
