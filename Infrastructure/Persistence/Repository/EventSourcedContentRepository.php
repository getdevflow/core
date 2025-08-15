<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Content\Repository\ContentAggregateRepository;
use App\Domain\Content\Services\ContentProjection;
use App\Infrastructure\Persistence\Trait\EventSourcedRepositoryAware;
use Codefy\Domain\EventSourcing\TransactionalEventStore;

class EventSourcedContentAggregateRepository implements ContentAggregateRepository
{
    use EventSourcedRepositoryAware;

    public function __construct(protected TransactionalEventStore $eventStore, protected ContentProjection $projection)
    {
    }
}
