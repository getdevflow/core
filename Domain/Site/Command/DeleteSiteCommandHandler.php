<?php

declare(strict_types=1);

namespace App\Domain\Site\Command;

use App\Domain\Site\Repository\SiteCommandRepository;
use Codefy\CommandBus\Command;
use Codefy\CommandBus\CommandHandler;
use Exception;

final class DeleteSiteCommandHandler implements CommandHandler
{
    public function __construct(public SiteCommandRepository $repository)
    {
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function handle(DeleteSiteCommand|Command $command): void
    {
        $this->repository->destroy(id: $command->id);
    }
}
