<?php

declare(strict_types=1);

namespace App\Domain\ContentType\Query;

use App\Domain\ContentType\Repository\ContentTypeQueryRepository;
use Codefy\QueryBus\Query;
use Codefy\QueryBus\QueryHandler;
use Exception;

class FindContentTypeByIdQueryHandler implements QueryHandler
{
    public function __construct(protected ContentTypeQueryRepository $repository)
    {
    }

    /**
     * @inheritDoc
     * @param FindContentTypeByIdQuery|Query $query
     * @return array|object|null
     * @throws Exception
     */
    public function handle(FindContentTypeByIdQuery|Query $query): array|null|object
    {
        return $this->repository->findById($query->contentTypeId->toNative());
    }
}
