<?php

declare(strict_types=1);

namespace App\Domain\Site\Command;

use App\Application\Devflow;
use App\Domain\Site\Model\Site;
use App\Domain\Site\Repository\SiteCommandRepository;
use Codefy\CommandBus\Command;
use Codefy\CommandBus\CommandHandler;
use Codefy\Domain\Aggregate\AggregateNotFoundException;
use Exception;

final class UpdateSiteCommandHandler implements CommandHandler
{
    public function __construct(public SiteCommandRepository $repository)
    {
    }

    /**
     * @inheritDoc
     * @throws AggregateNotFoundException
     * @throws Exception
     */
    public function handle(UpdateSiteCommand|Command $command): void
    {
        /** @var Site $site */
        $site = Devflow::$PHP->make(name: Site::class);
        $site->id = $command->id->toNative();
        $site->name = $command->name->toNative();
        $site->slug = $command->slug->toNative();
        $site->domain = $command->domain->toNative();
        $site->mapping = $command->mapping->toNative();
        $site->path = $command->path->toNative();
        $site->owner = $command->owner->toNative();
        $site->status = $command->status->toNative();
        $site->modified = $command->modified->format('Y-m-d H:i:s');

        $this->repository->update(site: $site);
    }
}
