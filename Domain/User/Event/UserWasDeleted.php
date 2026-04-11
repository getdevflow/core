<?php

declare(strict_types=1);

namespace App\Domain\User\Event;

use App\Domain\User\ValueObject\UserId;
use Codefy\Domain\Aggregate\AggregateId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;

class UserWasDeleted extends AggregateChanged
{
    private UserId $id;

    public static function withData(
        UserId $id,
    ): UserWasDeleted|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'user'
            ]
        );

        $event->id = $id;

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
}
