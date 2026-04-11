<?php

declare(strict_types=1);

namespace App\Domain\ContentType\Command;

use App\Domain\ContentType\ContentType;
use App\Domain\ContentType\Repository\ContentTypeAggregateRepository;
use Codefy\CommandBus\Command;
use Codefy\CommandBus\CommandHandler;
use Codefy\Domain\Aggregate\AggregateNotFoundException;
use Exception;

class UpdateContentTypeCommandHandler implements CommandHandler
{
    public function __construct(public ContentTypeAggregateRepository $aggregateRepository)
    {
    }

    /**
     * @inheritDoc
     * @throws AggregateNotFoundException
     * @throws Exception
     */
    public function handle(UpdateContentTypeCommand|Command $command): void
    {
        /** @var ContentType $contentType */
        $contentType = $this->aggregateRepository->loadAggregateRoot($command->id);

        $contentType->changeTitle($command->title);
        $contentType->changeContentTypeSlug($command->slug);
        $contentType->changeContentTypeDescription($command->description);

        $this->aggregateRepository->saveAggregateRoot($contentType);
    }
}
