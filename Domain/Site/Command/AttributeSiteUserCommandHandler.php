<?php

declare(strict_types=1);

namespace App\Domain\Site\Command;

use App\Domain\Site\Repository\SiteCommandRepository;
use Codefy\CommandBus\Command;
use Codefy\CommandBus\CommandHandler;
use Exception;

final class AttributeSiteUserCommandHandler implements CommandHandler
{
    public function __construct(public SiteCommandRepository $repository)
    {
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function handle(AttributeSiteUserCommand|Command $command): void
    {
        $this->repository->attributeSiteUser(siteId: $command->siteId, authorId: $command->authorId, assignId: $command->assignId);
    }
}
