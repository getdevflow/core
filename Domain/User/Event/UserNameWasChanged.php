<?php

declare(strict_types=1);

namespace App\Domain\User\Event;

use App\Domain\User\ValueObject\UserId;
use Codefy\Domain\Aggregate\AggregateId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;
use Qubus\ValueObjects\Person\Name;
use Qubus\ValueObjects\StringLiteral\StringLiteral;

use function Qubus\Support\Helpers\is_null__;

class UserNameWasChanged extends AggregateChanged
{
    private UserId $id;

    private Name $name;

    public static function withData(
        UserId $id,
        Name $name
    ): UserNameWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'user_fname' => $name->getFirstName()->toNative(),
                'user_mname' => $name->getMiddleName()->toNative(),
                'user_lname' => $name->getLastName()->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'user'
            ]
        );

        $event->id = $id;
        $event->name = $name;

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

    public function name(): Name
    {
        if (!isset($this->name)) {
            $this->name = Name::fromNative(
                $this->payload()['user_fname'],
                $this->payload()['user_mname'],
                $this->payload()['user_lname']
            );
        }

        return $this->name;
    }

    /**
     * @throws TypeException
     */
    public function userFname(): StringLiteral
    {
        if (!isset($this->name)) {
            $this->name = Name::fromNative(
                $this->payload()['user_fname'],
                $this->payload()['user_mname'],
                $this->payload()['user_lname']
            );
        }

        return $this->name->getFirstName();
    }

    public function userMname(): StringLiteral
    {
        if (!isset($this->name)) {
            $this->name = Name::fromNative(
                $this->payload()['user_fname'],
                $this->payload()['user_mname'],
                $this->payload()['user_lname']
            );
        }

        return $this->name->getMiddleName();
    }

    public function userLname(): StringLiteral
    {
        if (!isset($this->name)) {
            $this->name = Name::fromNative(
                $this->payload()['user_fname'],
                $this->payload()['user_mname'],
                $this->payload()['user_lname']
            );
        }

        return $this->name->getLastName();
    }
}
