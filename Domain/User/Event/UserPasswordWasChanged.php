<?php

declare(strict_types=1);

namespace App\Domain\User\Event;

use App\Domain\User\ValueObject\UserId;
use Codefy\Domain\Aggregate\AggregateId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;
use Qubus\ValueObjects\StringLiteral\StringLiteral;
use SensitiveParameter;

class UserPasswordWasChanged extends AggregateChanged
{
    private UserId $id;

    private StringLiteral $password;

    public static function withData(
        UserId $id,
        #[SensitiveParameter] StringLiteral $password
    ): UserPasswordWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'user_pass' => $password->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'user'
            ]
        );

        $event->id = $id;
        $event->password = $password;

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

    public function userPass(): StringLiteral
    {
        if (!isset($this->password)) {
            $this->password = StringLiteral::fromNative($this->payload()['user_pass']);
        }

        return $this->password;
    }
}
