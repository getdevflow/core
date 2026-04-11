<?php

declare(strict_types=1);

namespace App\Domain\Content\Event;

use App\Domain\Content\ValueObject\ContentId;
use App\Domain\User\ValueObject\UserId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;

final class ContentAuthorWasChanged extends AggregateChanged
{
    private ContentId $id;

    private UserId $author;

    public static function withData(
        ContentId $id,
        UserId $author,
    ): ContentAuthorWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'content_author' => $author->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'content',
            ]
        );

        $event->id = $id;
        $event->author = $author;

        return $event;
    }

    /**
     * @throws TypeException
     */
    public function contentId(): ContentId
    {
        if (!isset($this->id)) {
            $this->id = ContentId::fromString($this->aggregateId()->__toString());
        }

        return $this->id;
    }

    /**
     * @throws TypeException
     */
    public function contentAuthor(): UserId
    {
        if (!isset($this->author)) {
            $this->author = UserId::fromString($this->payload()['content_author']);
        }

        return $this->author;
    }
}
