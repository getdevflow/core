<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Content\Event;

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
     *     'meta': array<array-key, mixed>
     * } $content
     */
    public function __construct(
        public array $content,
    ) {
    }
}
