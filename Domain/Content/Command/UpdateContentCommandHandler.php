<?php

declare(strict_types=1);

namespace App\Domain\Content\Command;

use App\Domain\Content\Content;
use App\Domain\Content\Repository\ContentAggregateRepository;
use Codefy\CommandBus\Command;
use Codefy\CommandBus\CommandHandler;
use Codefy\Domain\Aggregate\AggregateNotFoundException;
use Exception;

use function Qubus\Support\Helpers\is_null__;
use function Qubus\Support\Helpers\now;

class UpdateContentCommandHandler implements CommandHandler
{
    public function __construct(public ContentAggregateRepository $aggregateRepository)
    {
    }

    /**
     * @inheritDoc
     * @throws AggregateNotFoundException
     * @throws Exception
     */
    public function handle(UpdateContentCommand|Command $command): void
    {
        /** @var Content $content */
        $content = $this->aggregateRepository->loadAggregateRoot($command->id);

        $content->changeContentTitle($command->title);
        $content->changeContentSlug($command->slug);
        $content->changeContentBody($command->body);
        $content->changeContentAuthor($command->author);
        $content->changeContentType($command->type);
        if (!is_null__($command->parent)) {
            $content->changeContentParent($command->parent);
        } else {
            $content->changeContentParentWasRemoved($command->parent);
        }
        $content->changeContentSidebar($command->sidebar);
        $content->changeContentShowInMenu($command->showInMenu);
        $content->changeContentShowInSearch($command->showInSearch);
        $content->changeContentFeaturedImage($command->featuredImage);
        $content->changeContentStatus($command->status);
        $content->changeContentAttribute($command->attribute);
        if ($command->published->format('Y-m-d H:i') !== $content->contentPublished()->format('Y-m-d H:i')) {
            $content->changeContentPublished($command->published);
            $content->changeContentPublishedGmt($command->publishedGmt);
        }
        if ($content->hasRecordedEvents()) {
            $content->changeContentModified($command->modified);
            $content->changeContentModifiedGmt($command->modifiedGmt);
        }

        $this->aggregateRepository->saveAggregateRoot($content);
    }
}
