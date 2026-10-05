<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\ContentType\Repository\ContentTypeAggregateRepository;
use App\Domain\ContentType\Services\ContentTypeProjection;
use App\Infrastructure\Persistence\Trait\EventSourcedRepositoryAware;
use Codefy\Domain\EventSourcing\TransactionalEventStore;
use Qubus\Expressive\Database;

class EventSourcedContentTypeRepository implements ContentTypeAggregateRepository
{
    use EventSourcedRepositoryAware;

    public function __construct(
        protected TransactionalEventStore $eventStore,
        protected ContentTypeProjection $projection,
        protected Database $dfdb
    ) {
    }
}
