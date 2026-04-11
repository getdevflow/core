<?php

declare(strict_types=1);

namespace App\Domain\User\Event;

use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\UserToken;
use Codefy\Domain\Aggregate\AggregateId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;
use SensitiveParameter;

class UserTokenWasChanged extends AggregateChanged
{
    private UserId $id;

    private UserToken $token;

    public static function withData(
        UserId $id,
        #[SensitiveParameter] UserToken $token
    ): UserTokenWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'user_token' => $token->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'user'
            ]
        );

        $event->id = $id;
        $event->token = $token;

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

    /**
     * @throws TypeException
     */
    public function userToken(): UserToken
    {
        if (!isset($this->token)) {
            $this->token = UserToken::fromString(userToken: $this->payload()['user_token']);
        }

        return $this->token;
    }
}
