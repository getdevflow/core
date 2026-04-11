<?php

declare(strict_types=1);

namespace App\Domain\Content\Command;

use App\Domain\Content\Content;
use App\Domain\Content\Repository\ContentAggregateRepository;
use Codefy\CommandBus\Command;
use Codefy\CommandBus\CommandHandler;
use Codefy\Domain\Aggregate\AggregateNotFoundException;
use Exception;

class UpdateContentAuthorCommandHandler implements CommandHandler
{
    public function __construct(public ContentAggregateRepository $aggregateRepository)
    {
    }

    /**
     * @inheritDoc
     * @throws AggregateNotFoundException
     * @throws Exception
     */
    public function handle(UpdateContentAuthorCommand|Command $command): void
    {
        /** @var Content $content */
        $content = $this->aggregateRepository->loadAggregateRoot($command->id);

        $content->changeContentAuthor($command->author);
        $content->changeContentModified($command->modified);
        $content->changeContentModifiedGmt($command->modifiedGmt);

        $this->aggregateRepository->saveAggregateRoot($content);
    }
}
