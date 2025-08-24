<?php

declare(strict_types=1);

namespace App\Domain\Product\Query;

use App\Domain\Product\Repository\ProductQueryRepository;
use Codefy\QueryBus\Query;
use Codefy\QueryBus\QueryHandler;

final class FindProductsQueryHandler implements QueryHandler
{
    public function __construct(protected ProductQueryRepository $repository)
    {
    }

    /**
     * @inheritDoc
     */
    public function handle(FindProductsQuery|Query $query): array
    {
        return $this->repository->findByFilters(
            productSku: $query->productSku,
            limit: $query->limit,
            offset: $query->offset,
            status: $query->status
        );
    }
}
