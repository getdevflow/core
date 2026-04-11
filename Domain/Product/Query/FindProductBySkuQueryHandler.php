<?php

declare(strict_types=1);

namespace App\Domain\Product\Query;

use App\Domain\Product\Repository\ProductQueryRepository;
use Codefy\QueryBus\Query;
use Codefy\QueryBus\QueryHandler;
use Exception;

final class FindProductBySkuQueryHandler implements QueryHandler
{
    public function __construct(protected ProductQueryRepository $repository)
    {
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function handle(FindProductBySkuQuery|Query $query): array|object
    {
        return $this->repository->findBySku($query->sku->toNative());
    }
}
