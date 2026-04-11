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

class UserDateFormatWasChanged extends AggregateChanged
{
    private UserId $id;

    private StringLiteral $dateFormat;

    public static function withData(
        UserId $id,
        StringLiteral $dateFormat
    ): UserDateFormatWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'user_date_format' => $dateFormat->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'user'
            ]
        );

        $event->id = $id;
        $event->dateFormat = $dateFormat;

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

    public function userDateFormat(): StringLiteral
    {
        if (!isset($this->dateFormat)) {
            $this->dateFormat = StringLiteral::fromNative($this->payload()['user_date_format']);
        }

        return $this->dateFormat;
    }
}
