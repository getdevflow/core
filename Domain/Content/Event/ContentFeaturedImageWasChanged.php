<?php

declare(strict_types=1);

namespace App\Domain\Content\Event;

use App\Domain\Content\ValueObject\ContentId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;
use Qubus\ValueObjects\StringLiteral\StringLiteral;

final class ContentFeaturedImageWasChanged extends AggregateChanged
{
    private ContentId $id;

    private StringLiteral $featuredImage;

    public static function withData(
        ContentId $id,
        StringLiteral $featuredImage,
    ): ContentFeaturedImageWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'content_featured_image' => $featuredImage->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'content',
            ]
        );

        $event->id = $id;
        $event->featuredImage = $featuredImage;

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

    public function contentFeaturedImage(): StringLiteral
    {
        if (!isset($this->featuredImage)) {
            $this->featuredImage = StringLiteral::fromNative($this->payload()['content_featured_image']);
        }

        return $this->featuredImage;
    }
}
