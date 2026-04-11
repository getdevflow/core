<?php

declare(strict_types=1);

namespace App\Domain\User\Event;

use App\Domain\User\ValueObject\UserId;
use App\Shared\Services\DateTime;
use Codefy\Domain\Aggregate\AggregateId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use DateTimeInterface;
use Qubus\Exception\Data\TypeException;
use Qubus\Support\DateTime\QubusDateTimeImmutable;

class UserModifiedWasChanged extends AggregateChanged
{
    private UserId $id;

    private DateTimeInterface $modified;

    public static function withData(
        UserId $id,
        DateTimeInterface $modified
    ): UserModifiedWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'user_modified' => (string) $modified
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'user'
            ]
        );

        $event->id = $id;
        $event->modified = $modified;

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

    public function userModified(): DateTimeInterface
    {
        if (!isset($this->modified)) {
            $this->modified = QubusDateTimeImmutable::createFromInterface(
                new DateTime($this->payload()['user_modified'])->getDateTime()
            );
        }

        return $this->modified;
    }
}
