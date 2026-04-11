<?php

declare(strict_types=1);

namespace App\Domain\Content\Event;

use App\Domain\Content\ValueObject\ContentId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;
use Qubus\ValueObjects\Number\IntegerNumber;

final class ContentSidebarWasChanged extends AggregateChanged
{
    private ContentId $id;

    private IntegerNumber $sidebar;

    public static function withData(
        ContentId $id,
        IntegerNumber $sidebar,
    ): ContentSidebarWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'content_sidebar' => $sidebar->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'content',
            ]
        );

        $event->id = $id;
        $event->sidebar = $sidebar;

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
    public function contentSidebar(): IntegerNumber
    {
        if (!isset($this->sidebar)) {
            $this->sidebar = IntegerNumber::fromNative($this->payload()['content_sidebar']);
        }

        return $this->sidebar;
    }
}
