<?php

declare(strict_types=1);

namespace App\Domain\Content\Query;

use App\Domain\Content\Repository\ContentQueryRepository;
use Codefy\QueryBus\Query;
use Codefy\QueryBus\QueryHandler;

final class FindContentQueryHandler implements QueryHandler
{
    public function __construct(protected ContentQueryRepository $repository)
    {
    }

    /**
     * @inheritDoc
     */
    public function handle(FindContentQuery|Query $query): array
    {
        return $this->repository->findByFilters(
            type: $query->type,
            limit: $query->limit,
            offset: $query->offset,
            status: $query->status
        );
    }
}
