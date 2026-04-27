<?php

declare(strict_types=1);

namespace App\Domain\User\Command;

use App\Application\Devflow;
use App\Domain\User\Model\User;
use App\Domain\User\Repository\UserCommandRepository;
use Codefy\CommandBus\Command;
use Codefy\CommandBus\CommandHandler;
use Codefy\Domain\Aggregate\AggregateNotFoundException;
use Codefy\Framework\Support\Password;
use Exception;

final readonly class UpdateUserPasswordCommandHandler implements CommandHandler
{
    public function __construct(public UserCommandRepository $repository)
    {
    }

    /**
     * @throws AggregateNotFoundException
     * @throws Exception
     */
    public function handle(UpdateUserPasswordCommand|Command $command): void
    {
        /** @var User $user */
        $user = Devflow::$PHP->make(name: User::class);
        $user->id = $command->id->toNative();
        $user->token = $command->token->toNative();
        $user->pass = Password::hash($command->pass->toNative());

        $this->repository->updatePassword(user: $user);
    }
}
