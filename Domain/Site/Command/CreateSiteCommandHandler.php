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
        $site->id = $command->id->toNative();
        $site->key = $command->key->toNative();
        $site->name = $command->name->toNative();
        $site->slug = $command->slug->toNative();
        $site->domain = $command->domain->toNative();
        $site->mapping = $command->mapping->toNative();
        $site->path = $command->path->toNative();
        $site->owner = $command->owner->toNative();
        $site->status = $command->status->toNative();
        $site->registered = $command->registered->format('Y-m-d H:i:s');

        $this->repository->save(site: $site);
    }
}
