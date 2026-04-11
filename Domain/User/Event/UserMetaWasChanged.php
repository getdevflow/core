<?php

declare(strict_types=1);

namespace App\Domain\User\Event;

use App\Domain\User\ValueObject\UserId;
use App\Shared\ValueObject\ArrayLiteral;
use Codefy\Domain\Aggregate\AggregateId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;

use function Qubus\Support\Helpers\is_null__;

class UserMetaWasChanged extends AggregateChanged
{
    private UserId $id;

    private ArrayLiteral $meta;

    public static function withData(
        UserId $id,
        ?ArrayLiteral $meta = null
    ): UserMetaWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'meta' => $meta->toNative()
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'user',
            ]
        );

        $event->id = $id;
        $event->meta = $meta;

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

    public function usermeta(): ArrayLiteral
    {
        if (!isset($this->meta)) {
            $this->meta = ArrayLiteral::fromNative($this->payload()['meta']);
        }

        return $this->meta;
    }
}
