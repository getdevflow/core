<?php

declare(strict_types=1);

namespace App\Domain\ContentType\Event;

use App\Domain\ContentType\ValueObject\ContentTypeId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;
use Qubus\ValueObjects\StringLiteral\StringLiteral;

class ContentTypeTitleWasChanged extends AggregateChanged
{
    private ContentTypeId $contentTypeId;

    private StringLiteral $contentTypeTitle;

    public static function withData(
        ContentTypeId $contentTypeId,
        StringLiteral $contentTypeTitle
    ): ContentTypeTitleWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $contentTypeId,
            payload: [
                'content_type_title' => $contentTypeTitle->toNative(),
            ],
            metadata:[
                Metadata::AGGREGATE_TYPE => 'contenttype',
            ],
        );

        $event->contentTypeId = $contentTypeId;
        $event->contentTypeTitle = $contentTypeTitle;

        return $event;
    }

    /**
     * @throws TypeException
     */
    public function contentTypeId(): ContentTypeId
    {
        if (!isset($this->contentTypeId)) {
            $this->contentTypeId = ContentTypeId::fromString($this->aggregateId()->__toString());
        }

        return $this->contentTypeId;
    }

    public function contentTypeTitle(): StringLiteral
    {
        if (!isset($this->contentTypeTitle)) {
            $this->contentTypeTitle = StringLiteral::fromNative($this->payload()['content_type_title']);
        }

        return $this->contentTypeTitle;
    }
}
