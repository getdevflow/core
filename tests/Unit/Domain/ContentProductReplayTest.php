<?php

declare(strict_types=1);

use App\Domain\Content\Content;
use App\Domain\Content\Command\UpdateContentCommand;
use App\Domain\Content\Command\UpdateContentCommandHandler;
use App\Domain\Content\Repository\ContentAggregateRepository;
use App\Domain\Content\ValueObject\ContentId;
use App\Domain\Product\Product;
use App\Domain\Product\ValueObject\ProductId;
use App\Domain\User\ValueObject\UserId;
use App\Shared\ValueObject\ArrayLiteral;
use Codefy\Domain\Aggregate\AggregateId;
use Codefy\Domain\Aggregate\RecordsEvents;
use Codefy\Domain\EventSourcing\EventStream;
use Qubus\ValueObjects\Money\Money;
use Qubus\ValueObjects\Number\IntegerNumber;
use Qubus\ValueObjects\StringLiteral\StringLiteral;

function replayFixture(string $domain, ?ContentId $parent = null): Content|Product
{
    $date = new DateTimeImmutable('2026-01-15 09:20:37', new DateTimeZone('America/Los_Angeles'));
    $arguments = [
        'id' => $domain === 'Content' ? ContentId::fromString() : ProductId::fromString(ContentId::fromString()->toNative()),
        'title' => new StringLiteral('Title'), 'slug' => new StringLiteral('title'),
        'body' => new StringLiteral('Body'), 'author' => UserId::fromString(ContentId::fromString()->toNative()),
        'showInMenu' => new IntegerNumber(0), 'showInSearch' => new IntegerNumber(1),
        'featuredImage' => new StringLiteral(''), 'status' => new StringLiteral('published'),
        'created' => $date, 'createdGmt' => $date->setTimezone(new DateTimeZone('GMT')),
        'published' => $date, 'publishedGmt' => $date->setTimezone(new DateTimeZone('GMT')),
        'attribute' => new ArrayLiteral(['color' => 'blue']),
    ];
    if ($domain === 'Content') {
        return Content::createContent(...$arguments, type: new StringLiteral('post'),
            sidebar: new IntegerNumber(0), parent: $parent);
    }

    return Product::createProduct(...$arguments, sku: new StringLiteral('SKU'),
        price: Money::fromNative(1250, 'USD'), purchaseUrl: new StringLiteral('https://example.com'));
}

function replaySerialized(Content|Product $aggregate): Content|Product
{
    $events = [];
    foreach ($aggregate->getRecordedEvents() as $event) {
        $events[] = $event::fromArray([
            'aggregateId' => $aggregate->aggregateId(),
            'payload' => json_decode(json_encode($event->payload(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR),
            'metadata' => $event->metadata(),
        ]);
    }

    return $aggregate::reconstituteFromEventStream(new EventStream($aggregate->aggregateId(), $events));
}

it('preserves creation dates through serialized replay under a different server timezone', function (string $domain): void {
    $aggregate = replayFixture($domain);
    $originalTimezone = date_default_timezone_get();
    try {
        date_default_timezone_set('Asia/Tokyo');
        $replayed = replaySerialized($aggregate);
        foreach (['Created', 'CreatedGmt', 'Published', 'PublishedGmt'] as $field) {
            $accessor = lcfirst($domain) . $field;
            expect($replayed->$accessor()->getTimestamp())->toBe($aggregate->$accessor()->getTimestamp())
                ->and($replayed->$accessor()->format('Y-m-d H:i:s'))->toBe($aggregate->$accessor()->format('Y-m-d H:i:s'));
        }
        $prefix = 'change' . $domain;
        $replayed->{$prefix . 'Published'}($aggregate->{lcfirst($domain) . 'Published'}());
        $replayed->{$prefix . 'PublishedGmt'}($aggregate->{lcfirst($domain) . 'PublishedGmt'}());
        expect($replayed->hasRecordedEvents())->toBeFalse();
    } finally {
        date_default_timezone_set($originalTimezone);
    }
})->with(['Content', 'Product']);

it('removes a parent exactly once and respects the expected parent', function (): void {
    $parent = ContentId::fromString();
    $content = replayFixture('Content', $parent);
    $content->clearRecordedEvents();
    $content->changeContentParentWasRemoved(ContentId::fromString());
    expect($content->hasRecordedEvents())->toBeFalse();
    $content->changeContentParentWasRemoved($parent);
    expect($content->contentParent())->toBeNull();
    $event = iterator_to_array($content->getRecordedEvents())[0];
    expect($event->payload()['content_parent'])->toBeNull();
    $content->clearRecordedEvents();
    $content->changeContentParentWasRemoved();
    expect($content->hasRecordedEvents())->toBeFalse();
});

it('handles unchanged parentless updates and independent publication changes', function (string $change): void {
    $content = replaySerialized(replayFixture('Content'));
    $repository = new class ($content) implements ContentAggregateRepository {
        public function __construct(private Content $content) {}
        public function loadAggregateRoot(AggregateId $aggregateId): ?RecordsEvents { return $this->content; }
        public function saveAggregateRoot(RecordsEvents $aggregate): void {}
    };
    $data = ['id' => $content->contentId()];
    foreach (['Title', 'Slug', 'Body', 'Author', 'Type', 'Sidebar', 'ShowInMenu', 'ShowInSearch',
        'FeaturedImage', 'Status', 'Attribute', 'Published', 'PublishedGmt'] as $field) {
        $accessor = $field === 'Type' ? 'contentTypeSlug' : 'content' . $field;
        $data[lcfirst($field)] = $content->$accessor();
    }
    if ($change !== 'none') {
        $data[$change] = DateTimeImmutable::createFromInterface($data[$change])->modify('+1 second');
    }
    $data['modified'] = new DateTimeImmutable('2026-02-01 12:00:00');
    $data['modifiedGmt'] = $data['modified'];
    (new UpdateContentCommandHandler($repository))->handle(new UpdateContentCommand($data));
    $types = array_map(fn ($event) => $event::class, iterator_to_array($content->getRecordedEvents()));
    if ($change === 'none') {
        expect($types)->toBe([]);
    } else {
        $expected = $change === 'published' ? 'ContentPublishedWasChanged' : 'ContentPublishedGmtWasChanged';
        expect($types)->toHaveCount(3)->toContain('App\\Domain\\Content\\Event\\' . $expected);
    }
})->with(['none', 'published', 'publishedGmt']);

it('replays all product changes with the same values as the live aggregate', function (): void {
    $product = replayFixture('Product');
    foreach (['Title', 'Slug', 'Body', 'Sku', 'PurchaseUrl', 'FeaturedImage', 'Status'] as $field) {
        $product->{'changeProduct' . $field}(new StringLiteral('changed-' . strtolower($field)));
    }
    $product->changeProductAuthor(UserId::fromString(ContentId::fromString()->toNative()));
    $product->changeProductPrice(Money::fromNative(9876, 'EUR'));
    $product->changeProductShowInMenu(new IntegerNumber(1));
    $product->changeProductShowInSearch(new IntegerNumber(0));
    $product->changeProductAttribute(new ArrayLiteral(['color' => 'red', 'sizes' => ['S', 'M']]));
    $date = new DateTimeImmutable('2026-11-01T01:30:42-07:00');
    foreach (['Published', 'PublishedGmt', 'Modified', 'ModifiedGmt'] as $field) {
        $product->{'changeProduct' . $field}($date);
    }
    $product->changeProductDeleted($product->productId());
    $replayed = replaySerialized($product);
    foreach (['Id', 'Title', 'Slug', 'Body', 'Author', 'Sku', 'Price', 'PurchaseUrl', 'ShowInMenu',
        'ShowInSearch', 'FeaturedImage', 'Status', 'Attribute'] as $field) {
        expect($replayed->{'product' . $field}()->equals($product->{'product' . $field}()))->toBeTrue();
    }
    foreach (['Published', 'PublishedGmt', 'Modified', 'ModifiedGmt'] as $field) {
        $replayed->{'changeProduct' . $field}($date);
    }
    expect($replayed->hasRecordedEvents())->toBeFalse();
});

it('reads historical GMT creation fields as GMT under a non-UTC server timezone', function (string $domain): void {
    $aggregate = replayFixture($domain);
    $event = iterator_to_array($aggregate->getRecordedEvents())[0];
    $payload = array_filter($event->payload(), fn ($key) => !str_ends_with($key, '_timezone'), ARRAY_FILTER_USE_KEY);
    $originalTimezone = date_default_timezone_get();
    try {
        date_default_timezone_set('Asia/Tokyo');
        $historical = $event::fromArray(['aggregateId' => $aggregate->aggregateId(), 'payload' => $payload]);
        foreach (['CreatedGmt', 'PublishedGmt'] as $field) {
            $accessor = lcfirst($domain) . $field;
            expect($historical->$accessor()->getTimestamp())->toBe($event->$accessor()->getTimestamp());
        }
    } finally {
        date_default_timezone_set($originalTimezone);
    }
})->with(['Content', 'Product']);
