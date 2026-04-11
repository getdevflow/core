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

class UserTimeFormatWasChanged extends AggregateChanged
{
    private UserId $id;

    private StringLiteral $timeFormat;

    public static function withData(
        UserId $id,
        StringLiteral $timeFormat
    ): UserTimeFormatWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'user_time_format' => $timeFormat->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'user'
            ]
        );

        $event->id = $id;
        $event->timeFormat = $timeFormat;

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

    public function userTimeFormat(): StringLiteral
    {
        if (!isset($this->timeFormat)) {
            $this->timeFormat = StringLiteral::fromNative($this->payload()['user_time_format']);
        }

        return $this->timeFormat;
    }
}
