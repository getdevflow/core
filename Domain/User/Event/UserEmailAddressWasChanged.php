<?php

declare(strict_types=1);

namespace App\Domain\User\Event;

use App\Domain\User\ValueObject\UserId;
use Codefy\Domain\Aggregate\AggregateId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;
use Qubus\ValueObjects\Web\EmailAddress;

class UserEmailAddressWasChanged extends AggregateChanged
{
    private UserId $id;

    private EmailAddress $email;

    public static function withData(
        UserId $id,
        EmailAddress $email
    ): UserEmailAddressWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'user_email' => $email->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'user'
            ]
        );

        $event->id = $id;
        $event->email = $email;

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

    public function userEmail(): EmailAddress
    {
        if (!isset($this->email)) {
            $this->email = EmailAddress::fromNative($this->payload()['user_email']);
        }

        return $this->email;
    }
}
