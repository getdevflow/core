<?php

declare(strict_types=1);

namespace App\Domain\Site\Command;

use App\Domain\Site\Repository\SiteCommandRepository;
use Codefy\CommandBus\Command;
use Codefy\CommandBus\CommandHandler;
use Exception;

final class RemoveSiteUserCommandHandler implements CommandHandler
{
    public function __construct(public SiteCommandRepository $repository)
    {
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function handle(RemoveSiteUserCommand|Command $command): void
    {
        $this->repository->remove(siteId: $command->siteId, userId: $command->userId);
    }
}
