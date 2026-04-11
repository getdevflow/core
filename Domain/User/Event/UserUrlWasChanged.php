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

use function Qubus\Support\Helpers\is_null__;

class UserUrlWasChanged extends AggregateChanged
{
    private UserId $id;

    private StringLiteral $url;

    public static function withData(
        UserId $id,
        StringLiteral $url
    ): UserUrlWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'user_url' => $url->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'user'
            ]
        );

        $event->id = $id;
        $event->url = $url;

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

    public function userUrl(): StringLiteral
    {
        if (!isset($this->url)) {
            $this->url = StringLiteral::fromNative($this->payload()['user_url']);
        }

        return $this->url;
    }
}
