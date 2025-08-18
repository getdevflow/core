<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\User\Repository\UserAggregateRepository;
use App\Domain\User\Services\UserProjection;
use App\Infrastructure\Persistence\Trait\EventSourcedRepositoryAware;
use Codefy\Domain\EventSourcing\TransactionalEventStore;

final class EventSourcedUserAggregateRepository implements UserAggregateRepository
{
    use EventSourcedRepositoryAware;

    public function __construct(protected TransactionalEventStore $eventStore, protected UserProjection $projection)
    {
    }
}