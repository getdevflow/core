<?php

declare(strict_types=1);

namespace App\Domain\Content\Query;

use App\Domain\Content\Repository\ContentQueryRepository;
use Codefy\QueryBus\Query;
use Codefy\QueryBus\QueryHandler;
use Exception;

final class FindContentByIdQueryHandler implements QueryHandler
{
    public function __construct(protected ContentQueryRepository $repository)
    {
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function handle(FindContentByIdQuery|Query $query): array|object
    {
        return $this->repository->findById($query->id->toNative());
    }
}
