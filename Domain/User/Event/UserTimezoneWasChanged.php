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

class UserTimezoneWasChanged extends AggregateChanged
{
    private UserId $id;

    private StringLiteral $timezone;

    public static function withData(
        UserId $id,
        StringLiteral $timezone
    ): UserTimezoneWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'user_timezone' => $timezone->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'user'
            ]
        );

        $event->id = $id;
        $event->timezone = $timezone;

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

    public function userTimezone(): StringLiteral
    {
        if (!isset($this->timezone)) {
            $this->timezone = StringLiteral::fromNative($this->payload()['user_timezone']);
        }

        return $this->timezone;
    }
}
