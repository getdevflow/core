<?php

declare(strict_types=1);

namespace App\Domain\Site\Command;

use App\Application\Devflow;
use App\Domain\Site\Model\Site;
use App\Domain\Site\Repository\SiteCommandRepository;
use Codefy\CommandBus\Command;
use Codefy\CommandBus\CommandHandler;
use Qubus\Exception\Exception;

final class CreateSiteCommandHandler implements CommandHandler
{
    public function __construct(public SiteCommandRepository $repository)
    {
    }

    /**
     * @inheritDoc
     * @param CreateSiteCommand|Command $command
     * @throws Exception
     */
    public function handle(CreateSiteCommand|Command $command): void
    {
        /** @var Site $site */
        $site = Devflow::$PHP->make(name: Site::class);
        $site->create([
            'site_id' => $command->id->toNative(),
            'site_key' => $command->key->toNative(),
            'site_name' => $command->name->toNative(),
            'site_slug' => $command->slug->toNative(),
            'site_domain' => $command->domain->toNative(),
            'site_mapping' => $command->mapping->toNative(),
            'site_path' => $command->path->toNative(),
            'site_owner' => $command->owner->toNative(),
            'site_status' => $command->status->toNative(),
            'site_registered' => $command->registered->format('Y-m-d H:i:s'),
        ]);

        $this->repository->save(site: $site);
    }
}
