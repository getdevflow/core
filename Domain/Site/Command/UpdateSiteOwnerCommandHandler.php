<?php

declare(strict_types=1);

namespace App\Domain\Site\Command;

use App\Domain\Site\Repository\SiteAggregateRepository;
use App\Domain\Site\Site;
use Codefy\CommandBus\Command;
use Codefy\CommandBus\CommandHandler;
use Codefy\Domain\Aggregate\AggregateNotFoundException;
use Exception;

final class UpdateSiteOwnerCommandHandler implements CommandHandler
{
    public function __construct(public SiteAggregateRepository $aggregateRepository)
    {
    }

    /**
     * @inheritDoc
     * @throws AggregateNotFoundException
     * @throws Exception
     */
    public function handle(UpdateSiteOwnerCommand|Command $command): void
    {
        /** @var Site $site */
        $site = $this->aggregateRepository->loadAggregateRoot($command->siteId);

        $site->changeSiteOwner($command->siteOwner);
        if ($site->hasRecordedEvents()) {
            $site->changeSiteModified($command->siteModified);
        }

        $this->aggregateRepository->saveAggregateRoot($site);
    }
}
