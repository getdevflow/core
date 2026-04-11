<?php

declare(strict_types=1);

namespace App\Domain\Product\Event;

use App\Domain\Product\ValueObject\ProductId;
use App\Domain\User\ValueObject\UserId;
use App\Shared\Services\DateTime;
use App\Shared\ValueObject\ArrayLiteral;
use Codefy\Domain\Aggregate\AggregateId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use DateTimeInterface;
use Qubus\Exception\Data\TypeException;
use Qubus\Support\DateTime\QubusDateTimeImmutable;
use Qubus\ValueObjects\Money\Money;
use Qubus\ValueObjects\Number\IntegerNumber;
use Qubus\ValueObjects\StringLiteral\StringLiteral;

final class ProductWasCreated extends AggregateChanged
{
    private ProductId $id;
    private StringLiteral $title;

    private StringLiteral $slug;

    private StringLiteral $body;

    private UserId $author;

    private StringLiteral $sku;

    private Money $price;

    private StringLiteral $purchaseUrl;

    private IntegerNumber $showInMenu;

    private IntegerNumber $showInSearch;

    private StringLiteral $featuredImage;

    private StringLiteral $status;

    private ArrayLiteral $attribute;

    private DateTimeInterface $created;

    private DateTimeInterface $createdGmt;

    private DateTimeInterface $published;

    private DateTimeInterface $publishedGmt;

    /**
     * @throws TypeException
     */
    public static function withData(
        ProductId $id,
        StringLiteral $title,
        StringLiteral $slug,
        StringLiteral $body,
        UserId $author,
        StringLiteral $sku,
        Money $price,
        StringLiteral $purchaseUrl,
        IntegerNumber $showInMenu,
        IntegerNumber $showInSearch,
        StringLiteral $featuredImage,
        StringLiteral $status,
        DateTimeInterface $created,
        DateTimeInterface $createdGmt,
        DateTimeInterface $published,
        DateTimeInterface $publishedGmt,
        ArrayLiteral $attribute,
    ): ProductWasCreated|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'product_id' => $id->toNative(),
                'product_title' => $title->toNative(),
                'product_slug' => $slug->toNative(),
                'product_body' => $body->toNative(),
                'product_attribute' => $attribute->toNative(),
                'product_author' => $author->toNative(),
                'product_sku' => $sku->toNative(),
                'product_price' => $price->getAmount()->toNative(),
                'product_currency' => $price->getCurrency()->getCode()->toNative(),
                'product_purchase_url' => $purchaseUrl->toNative(),
                'product_show_in_menu' => $showInMenu->toNative(),
                'product_show_in_search' => $showInSearch->toNative(),
                'product_featured_image' => $featuredImage->toNative(),
                'product_status' => $status->toNative(),
                'product_created' => $created->format('Y-m-d H:i:s'),
                'product_created_gmt' => $createdGmt->format('Y-m-d H:i:s'),
                'product_published' => $published->format('Y-m-d H:i:s'),
                'product_published_gmt' => $publishedGmt->format('Y-m-d H:i:s'),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'product',
            ],
        );

        $event->id = $id;
        $event->title = $title;
        $event->slug = $slug;
        $event->body = $body;
        $event->author = $author;
        $event->sku = $sku;
        $event->price = $price;
        $event->purchaseUrl = $purchaseUrl;
        $event->showInMenu = $showInMenu;
        $event->showInSearch = $showInSearch;
        $event->featuredImage = $featuredImage;
        $event->status = $status;
        $event->created = $created;
        $event->createdGmt = $createdGmt;
        $event->published = $published;
        $event->publishedGmt = $publishedGmt;
        $event->attribute = $attribute;

        return $event;
    }

    /**
     * @throws TypeException
     */
    public function productId(): ProductId|AggregateId
    {
        if (!isset($this->id)) {
            $this->id = ProductId::fromString(productId: $this->aggregateId()->__toString());
        }

        return $this->id;
    }

    public function productTitle(): StringLiteral
    {
        if (!isset($this->title)) {
            $this->title = StringLiteral::fromNative($this->payload()['product_title']);
        }

        return $this->title;
    }

    public function productSlug(): StringLiteral
    {
        if (!isset($this->slug)) {
            $this->slug = StringLiteral::fromNative($this->payload()['product_slug']);
        }

        return $this->slug;
    }

    public function productBody(): StringLiteral
    {
        if (!isset($this->body)) {
            $this->body = StringLiteral::fromNative($this->payload()['product_body']);
        }

        return $this->body;
    }

    /**
     * @throws TypeException
     */
    public function productAuthor(): UserId
    {
        if (!isset($this->author)) {
            $this->author = UserId::fromString($this->payload()['product_author']);
        }

        return $this->author;
    }

    public function productSku(): StringLiteral
    {
        if (!isset($this->sku)) {
            $this->sku = StringLiteral::fromNative($this->payload()['product_sku']);
        }

        return $this->sku;
    }

    /**
     * @throws TypeException
     */
    public function productPrice(): Money
    {
        if (!isset($this->price)) {
            $this->price = Money::fromNative(
                $this->payload()['product_price'],
                $this->payload()['product_currency']
            );
        }

        return $this->price;
    }

    public function productPurchaseUrl(): StringLiteral
    {
        if (!isset($this->purchaseUrl)) {
            $this->purchaseUrl = StringLiteral::fromNative($this->payload()['product_purchase_url']);
        }

        return $this->purchaseUrl;
    }

    /**
     * @throws TypeException
     */
    public function productShowInMenu(): IntegerNumber
    {
        if (!isset($this->showInMenu)) {
            $this->showInMenu = IntegerNumber::fromNative($this->payload()['product_show_in_menu']);
        }

        return $this->showInMenu;
    }

    /**
     * @throws TypeException
     */
    public function productShowInSearch(): IntegerNumber
    {
        if (!isset($this->showInSearch)) {
            $this->showInSearch = IntegerNumber::fromNative($this->payload()['product_show_in_search']);
        }

        return $this->showInSearch;
    }

    public function productFeaturedImage(): StringLiteral
    {
        if (!isset($this->featuredImage)) {
            $this->featuredImage = StringLiteral::fromNative($this->payload()['product_featured_image']);
        }

        return $this->featuredImage;
    }

    public function productStatus(): StringLiteral
    {
        if (!isset($this->status)) {
            $this->status = StringLiteral::fromNative($this->payload()['product_status']);
        }

        return $this->status;
    }

    public function productCreated(): DateTimeInterface
    {
        if (!isset($this->created)) {
            $this->created = QubusDateTimeImmutable::createFromInterface(
                new DateTime($this->payload()['product_created'])->getDateTime()
            );
        }

        return $this->created;
    }

    public function productCreatedGmt(): DateTimeInterface
    {
        if (!isset($this->createdGmt)) {
            $this->createdGmt = QubusDateTimeImmutable::createFromInterface(
                new DateTime($this->payload()['product_created_gmt'])->getDateTime()
            );
        }

        return $this->createdGmt;
    }

    public function productPublished(): DateTimeInterface
    {
        if (!isset($this->published)) {
            $this->published = QubusDateTimeImmutable::createFromInterface(
                new DateTime($this->payload()['product_published'])->getDateTime()
            );
        }

        return $this->published;
    }

    public function productPublishedGmt(): DateTimeInterface
    {
        if (!isset($this->publishedGmt)) {
            $this->publishedGmt = QubusDateTimeImmutable::createFromInterface(
                new DateTime($this->payload()['product_published_gmt'])->getDateTime()
            );
        }

        return $this->publishedGmt;
    }

    public function productAttribute(): ArrayLiteral
    {
        if (!isset($this->attribute)) {
            $this->attribute = ArrayLiteral::fromNative($this->payload()['product_attribute']);
        }

        return $this->attribute;
    }
}
