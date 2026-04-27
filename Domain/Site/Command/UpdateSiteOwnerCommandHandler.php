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

final class UpdateSiteOwnerCommandHandler implements CommandHandler
{
    public function __construct(public SiteCommandRepository $repository)
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
        $site = Devflow::$PHP->make(name: Site::class);
        $site->id = $command->id->toNative();
        $site->owner = $command->owner->toNative();
        $site->modified = $command->modified->format('Y-m-d H:i:s');

        $this->repository->updateOwner($site);
    }
}
