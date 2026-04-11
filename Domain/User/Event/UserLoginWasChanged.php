<?php

declare(strict_types=1);

namespace App\Domain\User\Event;

use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\Username;
use Codefy\Domain\Aggregate\AggregateId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;

class UserLoginWasChanged extends AggregateChanged
{
    private UserId $id;

    private Username $login;

    public static function withData(
        UserId $id,
        Username $login
    ): UserLoginWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'user_login' => $login->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'user'
            ]
        );

        $event->id = $id;
        $event->login = $login;

        return $event;
    }

    /**
     * @throws TypeException
     */
    public function userId(): UserId|AggregateId
    {
        if (!isset($this->id)) {
            $this->id = UserId::fromString(userId: $this->aggregateId()->__toString());
        }

        return $this->id;
    }

    /**
     * @throws TypeException
     */
    public function userLogin(): Username
    {
        if (!isset($this->login)) {
            $this->login = Username::fromString($this->payload()['user_login']);
        }

        return $this->login;
    }
}
