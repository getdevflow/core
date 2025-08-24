<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Product\Repository\ProductAggregateRepository;
use App\Domain\Product\Service\ProductProjection;
use App\Infrastructure\Persistence\Trait\EventSourcedRepositoryAware;
use Codefy\Domain\EventSourcing\TransactionalEventStore;

class EventSourcedProductRepository implements ProductAggregateRepository
{
    use EventSourcedRepositoryAware;

    public function __construct(protected TransactionalEventStore $eventStore, protected ProductProjection $projection)
    {
    }
}