<?php

declare(strict_types=1);

namespace App\Domain\Content\Event;

use App\Domain\Content\ValueObject\ContentId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;
use Qubus\ValueObjects\Number\IntegerNumber;

final class ContentShowInMenuWasChanged extends AggregateChanged
{
    private ContentId $id;

    private IntegerNumber $showInMenu;

    public static function withData(
        ContentId $id,
        IntegerNumber $showInMenu,
    ): ContentShowInMenuWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'content_show_in_menu' => $showInMenu->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'content',
            ]
        );

        $event->id = $id;
        $event->showInMenu = $showInMenu;

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
    public function contentShowInMenu(): IntegerNumber
    {
        if (!isset($this->showInMenu)) {
            $this->showInMenu = IntegerNumber::fromNative($this->payload()['content_show_in_menu']);
        }

        return $this->showInMenu;
    }
}
