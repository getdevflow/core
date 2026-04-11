<?php

declare(strict_types=1);

namespace App\Domain\Content\Query;

use App\Domain\Content\Repository\ContentQueryRepository;
use Codefy\QueryBus\Query;
use Codefy\QueryBus\QueryHandler;
use Exception;

final class FindContentByStatusQueryHandler implements QueryHandler
{
    public function __construct(protected ContentQueryRepository $repository)
    {
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function handle(FindContentByStatusQuery|Query $query): array
    {
        return $this->repository->findByStatus($query->status->toNative());
    }
}
