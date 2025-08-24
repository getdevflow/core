<?php

declare(strict_types=1);

namespace App\Domain\Site\Query;

use App\Domain\Site\Repository\SitesQueryRepository;
use Codefy\QueryBus\Query;
use Codefy\QueryBus\QueryHandler;

class FindSitesQueryHandler implements QueryHandler
{
    public function __construct(protected SitesQueryRepository $repository)
    {
    }

    public function handle(FindSitesQuery|Query $query): array
    {
        return $this->repository->findAll();
    }
}
