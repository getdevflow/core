<?php

declare(strict_types=1);

namespace App\Domain\ContentType\Query;

use App\Domain\ContentType\Repository\ContentTypeQueryRepository;
use Codefy\QueryBus\Query;
use Codefy\QueryBus\QueryHandler;
use Exception;

class FindContentTypeBySlugQueryHandler implements QueryHandler
{
    public function __construct(protected ContentTypeQueryRepository $repository)
    {
    }

    /**
     * @inheritDoc
     * @param FindContentTypeBySlugQuery|Query $query
     * @throws Exception
     */
    public function handle(FindContentTypeBySlugQuery|Query $query): array|null|object
    {
        return $this->repository->findBySlug($query->slug->toNative());
    }
}
