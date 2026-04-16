<?php

declare(strict_types=1);

namespace App\Domain\Content\Command;

use App\Domain\Content\Content;
use App\Domain\Content\Repository\ContentAggregateRepository;
use Codefy\CommandBus\Command;
use Codefy\CommandBus\CommandHandler;

class CreateContentCommandHandler implements CommandHandler
{
    public function __construct(public ContentAggregateRepository $aggregateRepository)
    {
    }

    /**
     * @inheritDoc
     */
    public function handle(CreateContentCommand|Command $command): void
    {
        /** @var CreateContentCommand $command */

        $content = Content::createContent(
            id: $command->id,
            title: $command->title,
            slug: $command->slug,
            body: $command->body,
            author: $command->author,
            type: $command->type,
            sidebar: $command->sidebar,
            showInMenu: $command->showInMenu,
            showInSearch: $command->showInSearch,
            featuredImage: $command->featuredImage,
            status: $command->status,
            created: $command->created,
            createdGmt: $command->createdGmt,
            published: $command->published,
            publishedGmt: $command->publishedGmt,
            attribute: $command->attribute,
            parent: $command->parent,
        );

        $this->aggregateRepository->saveAggregateRoot($content);
    }
}
