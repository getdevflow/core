<?php

declare(strict_types=1);

namespace App\Domain\Content\Event;

use App\Domain\Content\ValueObject\ContentId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;

use function Qubus\Support\Helpers\is_null__;

final class ContentParentWasRemoved extends AggregateChanged
{
    private ContentId $id;

    private ?ContentId $parent = null;

    public static function withData(
        ContentId $id,
        ?ContentId $parent = null,
    ): ContentParentWasRemoved|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'content_parent' => is_null__($parent) ? null : $parent->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'content',
            ]
        );

        $event->id = $id;
        $event->parent = $parent;

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
    public function contentParent(): ?ContentId
    {
        if (is_null__($this->parent)) {
            $this->parent = null;
        } else {
            $this->parent = ContentId::fromString($this->payload()['content_parent']);
        }

        return $this->parent;
    }
}
