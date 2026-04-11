<?php

declare(strict_types=1);

namespace App\Domain\Product;

use App\Domain\Product\Event\ProductAuthorWasChanged;
use App\Domain\Product\Event\ProductBodyWasChanged;
use App\Domain\Product\Event\ProductFeaturedImageWasChanged;
use App\Domain\Product\Event\ProductAttributeWasChanged;
use App\Domain\Product\Event\ProductModifiedGmtWasChanged;
use App\Domain\Product\Event\ProductModifiedWasChanged;
use App\Domain\Product\Event\ProductPriceWasChanged;
use App\Domain\Product\Event\ProductPublishedGmtWasChanged;
use App\Domain\Product\Event\ProductPublishedWasChanged;
use App\Domain\Product\Event\ProductPurchaseUrlWasChanged;
use App\Domain\Product\Event\ProductShowInMenuWasChanged;
use App\Domain\Product\Event\ProductShowInSearchWasChanged;
use App\Domain\Product\Event\ProductSkuWasChanged;
use App\Domain\Product\Event\ProductSlugWasChanged;
use App\Domain\Product\Event\ProductStatusWasChanged;
use App\Domain\Product\Event\ProductTitleWasChanged;
use App\Domain\Product\Event\ProductWasCreated;
use App\Domain\Product\Event\ProductWasDeleted;
use App\Domain\Product\ValueObject\ProductId;
use App\Domain\User\ValueObject\UserId;
use App\Shared\ValueObject\ArrayLiteral;
use Codefy\Domain\Aggregate\AggregateRoot;
use Codefy\Domain\Aggregate\EventSourcedAggregate;
use DateTimeInterface;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\ValueObjects\Money\Money;
use Qubus\ValueObjects\Number\IntegerNumber;
use Qubus\ValueObjects\StringLiteral\StringLiteral;

use function Qubus\Security\Helpers\t__;
use function Qubus\Support\Helpers\is_null__;

class Product extends EventSourcedAggregate implements AggregateRoot
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

    private ?DateTimeInterface $modified = null;

    private ?DateTimeInterface $modifiedGmt = null;

    /**
     * @throws TypeException
     */
    public static function createProduct(
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
    ): Product {
        $product = self::root(aggregateId: $id);

        $product->recordApplyAndPublishThat(
            ProductWasCreated::withData(
                id: $id,
                title: $title,
                slug: $slug,
                body: $body,
                author: $author,
                sku: $sku,
                price: $price,
                purchaseUrl: $purchaseUrl,
                showInMenu: $showInMenu,
                showInSearch: $showInSearch,
                featuredImage: $featuredImage,
                status: $status,
                created: $created,
                createdGmt: $createdGmt,
                published: $published,
                publishedGmt: $publishedGmt,
                attribute: $attribute
            )
        );

        return $product;
    }

    /**
     * @throws TypeException
     */
    public static function fromNative(string $productId): Product
    {
        return self::root(aggregateId: ProductId::fromString($productId));
    }

    public function productId(): ProductId
    {
        return $this->id;
    }

    public function productTitle(): StringLiteral
    {
        return $this->title;
    }

    public function productSlug(): StringLiteral
    {
        return $this->slug;
    }

    public function productBody(): StringLiteral
    {
        return $this->body;
    }

    public function productAuthor(): UserId
    {
        return $this->author;
    }

    public function productSku(): StringLiteral
    {
        return $this->sku;
    }

    public function productPrice(): Money
    {
        return $this->price;
    }

    public function productPurchaseUrl(): StringLiteral
    {
        return $this->purchaseUrl;
    }

    public function productShowInMenu(): IntegerNumber
    {
        return $this->showInMenu;
    }

    public function productShowInSearch(): IntegerNumber
    {
        return $this->showInSearch;
    }

    public function productFeaturedImage(): StringLiteral
    {
        return $this->featuredImage;
    }

    public function productStatus(): StringLiteral
    {
        return $this->status;
    }

    public function productCreated(): DateTimeInterface
    {
        return $this->created;
    }

    public function productCreatedGmt(): DateTimeInterface
    {
        return $this->createdGmt;
    }

    public function productPublished(): DateTimeInterface
    {
        return $this->published;
    }

    public function productPublishedGmt(): DateTimeInterface
    {
        return $this->publishedGmt;
    }

    public function productAttribute(): ArrayLiteral
    {
        return $this->attribute;
    }

    /**
     * @throws Exception
     */
    public function changeProductTitle(StringLiteral $productTitle): void
    {
        if ($productTitle->isEmpty()) {
            throw new Exception(message: t__(msgid: 'Product title cannot be empty.', domain: 'devflow'));
        }
        if ($productTitle->equals($this->title)) {
            return;
        }
        $this->recordApplyAndPublishThat(
            event: ProductTitleWasChanged::withData(id: $this->id, title: $productTitle)
        );
    }

    /**
     * @throws Exception
     */
    public function changeProductSlug(StringLiteral $productSlug): void
    {
        if ($productSlug->isEmpty()) {
            throw new Exception(message: t__(msgid: 'Product slug cannot be empty.', domain: 'devflow'));
        }
        if ($productSlug->equals($this->slug)) {
            return;
        }
        $this->recordApplyAndPublishThat(
            event: ProductSlugWasChanged::withData(id: $this->id, slug: $productSlug)
        );
    }

    /**
     * @throws Exception
     */
    public function changeProductBody(StringLiteral $productBody): void
    {
        if ($productBody->isEmpty()) {
            throw new Exception(message: t__(msgid: 'Product body cannot be empty.', domain: 'devflow'));
        }
        if ($productBody->equals($this->body)) {
            return;
        }
        $this->recordApplyAndPublishThat(ProductBodyWasChanged::withData($this->id, $productBody));
    }

    /**
     * @throws Exception
     */
    public function changeProductAuthor(UserId $productAuthor): void
    {
        if ($productAuthor->isEmpty()) {
            throw new Exception(message: t__(msgid: 'Product author cannot be empty.', domain: 'devflow'));
        }
        if ($productAuthor->equals($this->author)) {
            return;
        }
        $this->recordApplyAndPublishThat(ProductAuthorWasChanged::withData($this->id, $productAuthor));
    }

    /**
     * @throws Exception
     */
    public function changeProductSku(StringLiteral $productSku): void
    {
        if ($productSku->isEmpty()) {
            throw new Exception(message: t__(msgid: 'Product sku cannot be empty.', domain: 'devflow'));
        }
        if ($productSku->equals($this->sku)) {
            return;
        }
        $this->recordApplyAndPublishThat(ProductSkuWasChanged::withData($this->id, $productSku));
    }

    /**
     * @throws TypeException
     */
    public function changeProductPrice(Money $productPrice): void
    {
        if ($productPrice->getAmount()->toNative() < 0) {
            return;
        }
        if ($productPrice->equals($this->price)) {
            return;
        }
        $this->recordApplyAndPublishThat(ProductPriceWasChanged::withData($this->id, $productPrice));
    }

    public function changeProductPurchaseUrl(StringLiteral $productPurchaseUrl): void
    {
        if ($productPurchaseUrl->equals($this->purchaseUrl)) {
            return;
        }
        $this->recordApplyAndPublishThat(ProductPurchaseUrlWasChanged::withData($this->id, $productPurchaseUrl));
    }

    /**
     * @throws Exception
     */
    public function changeProductShowInMenu(IntegerNumber $productShowInMenu): void
    {
        if ($productShowInMenu->toNative() < 0) {
            throw new Exception(
                message: t__(msgid: 'Product show in menu must be an absolute value.', domain: 'devflow')
            );
        }
        if ($productShowInMenu->equals($this->showInMenu)) {
            return;
        }
        $this->recordApplyAndPublishThat(ProductShowInMenuWasChanged::withData($this->id, $productShowInMenu));
    }

    /**
     * @throws Exception
     */
    public function changeProductShowInSearch(IntegerNumber $productShowInSearch): void
    {
        if ($productShowInSearch->toNative() < 0) {
            throw new Exception(
                message: t__(msgid: 'Product show in search must be an absolute value.', domain: 'devflow')
            );
        }
        if ($productShowInSearch->equals($this->showInSearch)) {
            return;
        }
        $this->recordApplyAndPublishThat(
            ProductShowInSearchWasChanged::withData($this->id, $productShowInSearch)
        );
    }

    public function changeProductFeaturedImage(StringLiteral $productFeaturedImage): void
    {
        if ($productFeaturedImage->equals($this->featuredImage)) {
            return;
        }
        $this->recordApplyAndPublishThat(
            ProductFeaturedImageWasChanged::withData($this->id, $productFeaturedImage)
        );
    }

    /**
     * @throws Exception
     */
    public function changeProductStatus(StringLiteral $productStatus): void
    {
        if ($productStatus->isEmpty()) {
            throw new Exception(message: t__(msgid: 'Product status cannot be empty.', domain: 'devflow'));
        }
        if ($productStatus->equals($this->status)) {
            return;
        }
        $this->recordApplyAndPublishThat(ProductStatusWasChanged::withData($this->id, $productStatus));
    }

    public function changeProductAttribute(ArrayLiteral $attribute): void
    {
        if ($attribute->equals($this->attribute)) {
            return;
        }

        $this->recordApplyAndPublishThat(ProductAttributeWasChanged::withData($this->id, $attribute));
    }

    /**
     * @throws Exception
     */
    public function changeProductPublished(DateTimeInterface $productPublished): void
    {
        if (empty($this->published)) {
            throw new Exception(message: t__(msgid: 'Product published date cannot be empty.', domain: 'devflow'));
        }
        if ($this->published->getTimestamp() === $productPublished->getTimestamp()) {
            return;
        }
        $this->recordApplyAndPublishThat(ProductPublishedWasChanged::withData($this->id, $productPublished));
    }

    /**
     * @throws Exception
     */
    public function changeProductPublishedGmt(DateTimeInterface $productPublishedGmt): void
    {
        if (empty($this->publishedGmt)) {
            throw new Exception(message: t__(msgid: 'Product published gmt date cannot be empty.', domain: 'devflow'));
        }
        if ($this->publishedGmt->getTimestamp() === $productPublishedGmt->getTimestamp()) {
            return;
        }
        $this->recordApplyAndPublishThat(
            ProductPublishedGmtWasChanged::withData($this->id, $productPublishedGmt)
        );
    }

    public function changeProductModified(DateTimeInterface $productModified): void
    {
        if (
                !is_null__($this->modified) &&
                ($this->modified->getTimestamp() === $productModified->getTimestamp())
        ) {
            return;
        }
        $this->recordApplyAndPublishThat(ProductModifiedWasChanged::withData($this->id, $productModified));
    }

    public function changeProductModifiedGmt(DateTimeInterface $productModifiedGmt): void
    {
        if (
                !is_null__($this->modifiedGmt) &&
                ($this->modifiedGmt->getTimestamp() === $productModifiedGmt->getTimestamp())
        ) {
            return;
        }
        $this->recordApplyAndPublishThat(ProductModifiedGmtWasChanged::withData($this->id, $productModifiedGmt));
    }

    /**
     * @throws \Exception
     */
    public function changeProductDeleted(ProductId $productId): void
    {
        if ($productId->isEmpty()) {
            throw new \Exception(message: t__(msgid: 'Product id cannot be null.', domain: 'devflow'));
        }
        if (!$productId->equals($this->id)) {
            return;
        }
        $this->recordApplyAndPublishThat(ProductWasDeleted::withData($this->id));
    }

    /**
     * @throws TypeException
     */
    public function whenProductWasCreated(ProductWasCreated $event): void
    {
        $this->id = $event->productId();
        $this->title = $event->productTitle();
        $this->slug = $event->productSlug();
        $this->body = $event->productBody();
        $this->author = $event->productAuthor();
        $this->sku = $event->productSku();
        $this->price = $event->productPrice();
        $this->purchaseUrl = $event->productPurchaseUrl();
        $this->showInMenu = $event->productShowInMenu();
        $this->showInSearch = $event->productShowInSearch();
        $this->featuredImage = $event->productFeaturedImage();
        $this->status = $event->productStatus();
        $this->created = $event->productCreated();
        $this->createdGmt = $event->productCreatedGmt();
        $this->published = $event->productPublished();
        $this->publishedGmt = $event->productPublishedGmt();
        $this->attribute = $event->productAttribute();
    }

    /**
     * @throws TypeException
     */
    public function whenProductTitleWasChanged(ProductTitleWasChanged $event): void
    {
        $this->id = $event->productId();
        $this->title = $event->productTitle();
    }

    /**
     * @throws TypeException
     */
    public function whenProductSlugWasChanged(ProductSlugWasChanged $event): void
    {
        $this->id = $event->productId();
        $this->slug = $event->productSlug();
    }

    /**
     * @throws TypeException
     */
    public function whenProductBodyWasChanged(ProductBodyWasChanged $event): void
    {
        $this->id = $event->productId();
        $this->body = $event->productBody();
    }

    /**
     * @throws TypeException
     */
    public function whenProductAuthorWasChanged(ProductAuthorWasChanged $event): void
    {
        $this->id = $event->productId();
        $this->author = $event->productAuthor();
    }

    /**
     * @throws TypeException
     */
    public function whenProductSkuWasChanged(ProductSkuWasChanged $event): void
    {
        $this->id = $event->productId();
        $this->sku = $event->productSku();
    }

    /**
     * @throws TypeException
     */
    public function whenProductPriceWasChanged(ProductPriceWasChanged $event): void
    {
        $this->id = $event->productId();
        $this->price = $event->productPrice();
    }

    /**
     * @throws TypeException
     */
    public function whenProductPurchaseUrlWasChanged(ProductPurchaseUrlWasChanged $event): void
    {
        $this->id = $event->productId();
        $this->purchaseUrl = $event->productPurchaseUrl();
    }

    /**
     * @throws TypeException
     */
    public function whenProductShowInMenuWasChanged(ProductShowInMenuWasChanged $event): void
    {
        $this->id = $event->productId();
        $this->showInMenu = $event->productShowInMenu();
    }

    /**
     * @throws TypeException
     */
    public function whenProductShowInSearchWasChanged(ProductShowInSearchWasChanged $event): void
    {
        $this->id = $event->productId();
        $this->showInSearch = $event->productShowInSearch();
    }

    /**
     * @throws TypeException
     */
    public function whenProductFeaturedImageWasChanged(ProductFeaturedImageWasChanged $event): void
    {
        $this->id = $event->productId();
        $this->featuredImage = $event->productFeaturedImage();
    }

    /**
     * @throws TypeException
     */
    public function whenProductStatusWasChanged(ProductStatusWasChanged $event): void
    {
        $this->id = $event->productId();
        $this->status = $event->productStatus();
    }

    /**
     * @throws TypeException
     */
    public function whenProductAttributeWasChanged(ProductAttributeWasChanged $event): void
    {
        $this->id = $event->productId();
        $this->attribute = $event->productAttribute();
    }

    /**
     * @throws TypeException
     */
    public function whenProductPublishedWasChanged(ProductPublishedWasChanged $event): void
    {
        $this->id = $event->productId();
        $this->published = $event->productPublished();
    }

    /**
     * @throws TypeException
     */
    public function whenProductPublishedGmtWasChanged(ProductPublishedGmtWasChanged $event): void
    {
        $this->id = $event->productId();
        $this->publishedGmt = $event->productPublishedGmt();
    }

    /**
     * @throws TypeException
     */
    public function whenProductModifiedWasChanged(ProductModifiedWasChanged $event): void
    {
        $this->id = $event->productId();
        $this->modified = $event->productModified();
    }

    /**
     * @throws TypeException
     */
    public function whenProductModifiedGmtWasChanged(ProductModifiedGmtWasChanged $event): void
    {
        $this->id = $event->productId();
        $this->modifiedGmt = $event->productModifiedGmt();
    }

    /**
     * @throws TypeException
     */
    public function whenProductWasDeleted(ProductWasDeleted $event): void
    {
        $this->id = $event->productId();
    }
}
