<?php

declare(strict_types=1);

namespace App\Domain\User\Command;

use App\Domain\User\Repository\UserCommandRepository;
use Codefy\CommandBus\Command;
use Codefy\CommandBus\CommandHandler;
use Exception;

final readonly class DeleteUserCommandHandler implements CommandHandler
{
    public function __construct(public UserCommandRepository $repository)
    {
    }

    /**
     * @throws Exception
     */
    public function handle(DeleteUserCommand|Command $command): void
    {
        $this->repository->destroy(id: $command->id);
    }
}
