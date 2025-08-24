<?php

declare(strict_types=1);

namespace App\Domain\Content\Query;

use App\Domain\Content\Repository\ContentQueryRepository;
use Codefy\QueryBus\Query;
use Codefy\QueryBus\QueryHandler;
use Exception;

final class FindContentByTypeAndIdQueryHandler implements QueryHandler
{
    public function __construct(protected ContentQueryRepository $repository)
    {
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function handle(FindContentByTypeAndIdQuery|Query $query): array|object
    {
        return $this->repository->findByTypeAndId($query->contentTypeSlug->toNative(), $query->contentId->toNative());
    }
}
