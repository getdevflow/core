<?php

declare(strict_types=1);

namespace App\Domain\ContentType\Command;

use App\Domain\ContentType\ContentType;
use App\Domain\ContentType\Repository\ContentTypeAggregateRepository;
use Codefy\CommandBus\Command;
use Codefy\CommandBus\CommandHandler;
use Codefy\Domain\Aggregate\AggregateNotFoundException;
use Exception;

class DeleteContentTypeCommandHandler implements CommandHandler
{
    public function __construct(public ContentTypeAggregateRepository $aggregateRepository)
    {
    }

    /**
     * @inheritDoc
     * @throws AggregateNotFoundException
     * @throws Exception
     */
    public function handle(DeleteContentTypeCommand|Command $command): void
    {
        /** @var ContentType $contentType */
        $contentType = $this->aggregateRepository->loadAggregateRoot($command->id);

        $contentType->changeContentTypeDeleted($command->id);

        $this->aggregateRepository->saveAggregateRoot($contentType);
    }
}
