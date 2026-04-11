<?php

declare(strict_types=1);

namespace App\Domain\User\Command;

use App\Application\Devflow;
use App\Domain\User\Model\User;
use App\Domain\User\Repository\UserCommandRepository;
use Codefy\CommandBus\Command;
use Codefy\CommandBus\CommandHandler;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;

use function App\Shared\Helpers\update_user_attribute;
use function App\Shared\Helpers\update_usermeta;

final readonly class UpdateUserCommandHandler implements CommandHandler
{
    public function __construct(public UserCommandRepository $repository)
    {
    }

    /**
     * @param UpdateUserCommand|Command $command
     * @throws Exception
     */
    public function handle(UpdateUserCommand|Command $command): void
    {
        /** @var User $user */
        $user = Devflow::$PHP->make(name: User::class);
        $user->id = $command->id->toNative();
        $user->login = $command->login->toNative();
        $user->token = $command->token->toNative();
        $user->fname = $command->fname->toNative();
        $user->mname = isset($command->mname) ? $command->mname->toNative() : '';
        $user->lname = $command->lname->toNative();
        $user->email = $command->email->toNative();
        $user->url = $command->url->toNative();
        $user->bio = $command->bio->toNative();
        $user->timezone = $command->timezone->toNative();
        $user->dateFormat = $command->dateFormat->toNative();
        $user->timeFormat = $command->timeFormat->toNative();
        $user->locale = $command->locale->toNative();
        $user->activationKey = isset($command->activationKey) ? $command->activationKey->toNative() : null;
        $user->modified = $command->modified->format('Y-m-d H:i:s');

        $this->repository->update(user: $user);
    }
}
