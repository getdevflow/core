<?php

declare(strict_types=1);

namespace App\Domain\Content\Command;

use App\Domain\Content\Content;
use App\Domain\Content\Repository\ContentAggregateRepository;
use Codefy\CommandBus\Command;
use Codefy\CommandBus\CommandHandler;
use Codefy\Domain\Aggregate\AggregateNotFoundException;
use Exception;

class ContentWorkflowUpdateCommandHandler implements CommandHandler
{
    public function __construct(public ContentAggregateRepository $aggregateRepository)
    {
    }

    /**
     * @inheritDoc
     * @throws AggregateNotFoundException
     * @throws Exception
     */
    public function handle(ContentWorkflowUpdateCommand|Command $command): void
    {
        /** @var Content $content */
        $content = $this->aggregateRepository->loadAggregateRoot($command->id);

        $content->changeContentStatus($command->status);
        $content->changeContentAttribute($command->attribute);
        $content->changeContentModified($command->modified);
        $content->changeContentModifiedGmt($command->modifiedGmt);

        $this->aggregateRepository->saveAggregateRoot($content);
    }
}
