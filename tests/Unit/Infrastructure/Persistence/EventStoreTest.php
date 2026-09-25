<?php

declare(strict_types=1);

use App\Domain\Content\Event\ContentTitleWasChanged;
use App\Domain\Content\ValueObject\ContentId;
use App\Infrastructure\Persistence\QueryBuilderTransactionalEventStore;
use Codefy\Domain\Metadata;
use Qubus\Expressive\Connection\Pdo\Sqlite;
use Qubus\Expressive\QueryBuilder;
use Qubus\ValueObjects\StringLiteral\StringLiteral;

beforeEach(function (): void {
    $this->connection = new Sqlite(['dsn' => 'sqlite::memory:']);
    $this->connection->pdo->exec('CREATE TABLE event_store (
        event_id TEXT PRIMARY KEY, transaction_id TEXT, event_type TEXT, event_classname TEXT,
        payload TEXT, metadata TEXT, aggregate_id TEXT, aggregate_type TEXT,
        aggregate_playhead INTEGER, recorded_at TEXT
    )');
    $database = new class ($this->connection) extends QueryBuilder {
        public string $prefix = '';
    };
    $this->store = new QueryBuilderTransactionalEventStore($database);
    $this->aggregateId = ContentId::fromString();
    $this->event = fn (int $playhead, string $title = 'Title') => ContentTitleWasChanged::withData(
        $this->aggregateId,
        new StringLiteral($title)
    )->withAddedMetadata(Metadata::AGGREGATE_PLAYHEAD, $playhead);
});

it('rolls back the entire event batch when an append fails', function (): void {
    $event = ($this->event)(1);
    expect(fn () => $this->store->commit($event, $event))->toThrow(Exception::class);
    expect((int) $this->connection->pdo->query('SELECT COUNT(*) FROM event_store')->fetchColumn())->toBe(0);
});

it('rejects invalid JSON without committing earlier events in the batch', function (): void {
    expect(fn () => $this->store->commit(($this->event)(1), ($this->event)(2, "\xB1\x31")))
        ->toThrow(JsonException::class);
    expect((int) $this->connection->pdo->query('SELECT COUNT(*) FROM event_store')->fetchColumn())->toBe(0);
});

it('loads events in playhead order and includes all events at or above the requested playhead', function (): void {
    $this->store->commit(($this->event)(2), ($this->event)(0), ($this->event)(1));
    $history = iterator_to_array($this->store->getAggregateHistoryFor($this->aggregateId));
    expect(array_map(fn ($event) => $event->playhead(), $history))->toBe([0, 1, 2]);
    $history = iterator_to_array($this->store->loadFromPlayhead($this->aggregateId, 1));
    expect(array_map(fn ($event) => $event->playhead(), $history))->toBe([1, 2]);
});

it('rolls back stored events on projection failure without clearing pending aggregate events', function (): void {
    $database = new class ($this->connection) extends QueryBuilder {
        public string $prefix = '';
    };
    $event = ($this->event)(0);
    $aggregate = $this->createMock(\Codefy\Domain\Aggregate\RecordsEvents::class);
    $aggregate->method('getRecordedEvents')->willReturn(\Codefy\Domain\EventSourcing\DomainEvents::fromArray([$event]));
    $aggregate->expects($this->never())->method('clearRecordedEvents');
    $projection = $this->createMock(\App\Domain\Content\Services\ContentProjection::class);
    $projection->method('project')->willThrowException(new RuntimeException('Projection failed'));
    $repository = new \App\Infrastructure\Persistence\Repository\EventSourcedContentRepository(
        $this->store, $projection, $database
    );
    expect(fn () => $repository->saveAggregateRoot($aggregate))->toThrow(RuntimeException::class, 'Projection failed');
    expect((int) $this->connection->pdo->query('SELECT COUNT(*) FROM event_store')->fetchColumn())->toBe(0);
});
