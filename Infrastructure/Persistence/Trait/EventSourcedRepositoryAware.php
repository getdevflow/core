<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Trait;

use Codefy\Domain\Aggregate\AggregateId;
use Codefy\Domain\Aggregate\RecordsEvents;
use Codefy\Domain\EventSourcing\CorruptEventStreamException;
use Codefy\Traits\IdentityMapAware;
use Exception;

trait EventSourcedRepositoryAware
{
    use IdentityMapAware;

    /**
     * {@inheritDoc}
     * @throws CorruptEventStreamException
     */
    public function loadAggregateRoot(AggregateId $aggregateId): RecordsEvents
    {
        $cached = $this->retrieveFromIdentityMap($aggregateId);
        if ($cached !== null) {
            return $cached;
        }

        $aggregateRootClassName = $aggregateId->aggregateClassName();

        $aggregateHistory = $this->eventStore->getAggregateHistoryFor(aggregateId: $aggregateId);
        $eventSourcedAggregate = $aggregateRootClassName::reconstituteFromEventStream(
            aggregateHistory: $aggregateHistory
        );

        $this->attachToIdentityMap($eventSourcedAggregate);

        return $eventSourcedAggregate;
    }

    /**
     * {@inheritDoc}
     * @throws Exception
     */
    public function saveAggregateRoot(RecordsEvents $aggregate): void
    {
        $events = iterator_to_array($aggregate->getRecordedEvents());

        $this->dfdb->transactional(function () use ($events): void {
            $transaction = $this->eventStore->commit(...$events);
            $this->projection->project(...$transaction->committedEvents);
        });

        $aggregate->clearRecordedEvents();

        $this->removeFromIdentityMap($aggregate);
    }
}
