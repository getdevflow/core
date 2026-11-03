<?php

declare(strict_types=1);

use App\Domain\Content\Event\ContentModifiedGmtWasChanged;
use App\Domain\Content\Event\ContentModifiedWasChanged;
use App\Domain\Content\Event\ContentPublishedGmtWasChanged;
use App\Domain\Content\Event\ContentPublishedWasChanged;
use App\Domain\Content\ValueObject\ContentId;
use App\Domain\Product\Event\ProductModifiedGmtWasChanged;
use App\Domain\Product\Event\ProductModifiedWasChanged;
use App\Domain\Product\Event\ProductPublishedGmtWasChanged;
use App\Domain\Product\Event\ProductPublishedWasChanged;
use App\Domain\Product\ValueObject\ProductId;

it('serializes portable date interfaces and rehydrates them', function (
    string $eventClass,
    string $payloadKey,
    string $accessor,
    string $identityType,
    string $expectedTimezone
): void {
    $contentId = ContentId::fromString();
    $identity = $identityType === 'content'
        ? $contentId
        : ProductId::fromString($contentId->toNative());
    $date = new DateTimeImmutable('2024-04-05 12:30:45', new DateTimeZone($expectedTimezone));

    $event = $eventClass::withData($identity, $date);

    expect($event->payload()[$payloadKey])->toBe('2024-04-05 12:30:45');

    $rehydrated = $eventClass::fromArray([
        'aggregateId' => $identity,
        'payload' => [$payloadKey => '2024-04-05 12:30:45'],
    ]);
    $rehydratedDate = $rehydrated->{$accessor}();

    expect($rehydratedDate->format('Y-m-d H:i:s'))->toBe('2024-04-05 12:30:45')
        ->and($rehydratedDate->getTimezone()->getName())->toBe($expectedTimezone);

    $originalTimezone = date_default_timezone_get();
    try {
        foreach (['2026-11-01T01:30:42-07:00', '2026-11-01T01:30:42-08:00'] as $value) {
            $source = new DateTimeImmutable($value);
            $fresh = $eventClass::withData($identity, $source);
            date_default_timezone_set('Asia/Tokyo');
            $replayed = $eventClass::fromArray([
                'aggregateId' => $identity,
                'payload' => $fresh->payload(),
            ]);
            expect($replayed->{$accessor}()->getTimestamp())->toBe($source->getTimestamp());
        }
    } finally {
        date_default_timezone_set($originalTimezone);
    }

})->with([
    [ContentModifiedWasChanged::class, 'content_modified', 'contentModified', 'content', 'UTC'],
    [ContentModifiedGmtWasChanged::class, 'content_modified_gmt', 'contentModifiedGmt', 'content', 'GMT'],
    [ContentPublishedWasChanged::class, 'content_published', 'contentPublished', 'content', 'UTC'],
    [ContentPublishedGmtWasChanged::class, 'content_published_gmt', 'contentPublishedGmt', 'content', 'GMT'],
    [ProductModifiedWasChanged::class, 'product_modified', 'productModified', 'product', 'UTC'],
    [ProductModifiedGmtWasChanged::class, 'product_modified_gmt', 'productModifiedGmt', 'product', 'GMT'],
    [ProductPublishedWasChanged::class, 'product_published', 'productPublished', 'product', 'UTC'],
    [ProductPublishedGmtWasChanged::class, 'product_published_gmt', 'productPublishedGmt', 'product', 'GMT'],
]);
