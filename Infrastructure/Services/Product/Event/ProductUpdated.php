<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Product\Event;

use Qubus\Support\DateTime\QubusDateTimeImmutable;

final readonly class ProductUpdated
{
    /**
     * @param array{
     *     'id': string,
     *     'title': string,
     *     'slug': string,
     *     'body': string,
     *     'author': string,
     *     'sku': string,
     *     'price': float,
     *     'currency': string,
     *     'purchaseUrl': int,
     *     'showInMenu': int,
     *     'showInSearch': int,
     *     'featuredImage': string,
     *     'status': string,
     *     'created': string,
     *     'createdGmt': string,
     *     'published': string,
     *     'publishedGmt': string,
     *     'published': string,
     *     'publishedGmt': string,
     * } $product
     */
    public function __construct(
        public array $product,
        public string|null $actorId = null,
        public array $context = [],
        public QubusDateTimeImmutable $occurredAt = new QubusDateTimeImmutable(time: 'now'),
    ) {
    }
}
