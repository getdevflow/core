<?php

declare(strict_types=1);

namespace App\Domain\ContentType\Command;

use App\Domain\ContentType\ContentType;
use App\Domain\ContentType\Repository\ContentTypeAggregateRepository;
use Codefy\CommandBus\Command;
use Codefy\CommandBus\CommandHandler;

class CreateContentTypeCommandHandler implements CommandHandler
{
    public function __construct(public ContentTypeAggregateRepository $aggregateRepository)
    {
    }

    /**
     * @inheritDoc
     */
    public function handle(CreateContentTypeCommand|Command $command): void
    {
        $contentType = ContentType::createContentType(
            contentTypeId: $command->id,
            contentTypeTitle: $command->title,
            contentTypeSlug: $command->slug,
            contentTypeDescription: $command->description,
        );

        $this->aggregateRepository->saveAggregateRoot($contentType);
    }
}
