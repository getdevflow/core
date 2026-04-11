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
        $site->create([
            'site_id' => $command->id->toNative(),
            'site_name' => $command->name->toNative(),
            'site_slug' => $command->slug->toNative(),
            'site_domain' => $command->domain->toNative(),
            'site_mapping' => $command->mapping->toNative(),
            'site_path' => $command->path->toNative(),
            'site_owner' => $command->owner->toNative(),
            'site_status' => $command->status->toNative(),
            'site_modified' => $command->modified->format('Y-m-d H:i:s'),
        ]);

        $this->repository->update(site: $site);
    }
}
