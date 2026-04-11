<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\ContentType\Event;

final readonly class ContentTypeCreated
{
    /**
     * @param array{
     *     'id': string,
     *     'title': string,
     *     'slug': string,
     *     'description': string,
     * } $contentType
     */
    public function __construct(
        public array $contentType,
    ) {
    }
}
