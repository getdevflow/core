<?php

declare(strict_types=1);

namespace App\Domain\Product\Dto;

use App\Domain\Product\ValueObject\ProductId;
use App\Domain\User\ValueObject\UserId;
use App\Shared\Services\DateTime;
use App\Shared\ValueObject\ArrayLiteral;
use Codefy\Framework\Dto\DataTransformer;
use Codefy\Framework\Validation\DataValidator;
use DateTimeInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Support\DateTime\QubusDateTimeImmutable;
use Qubus\ValueObjects\Money\Currency;
use Qubus\ValueObjects\Money\CurrencyCode;
use Qubus\ValueObjects\Money\Money;
use Qubus\ValueObjects\Number\IntegerNumber;
use Qubus\ValueObjects\StringLiteral\StringLiteral;
use ReflectionException;

use function App\Shared\Helpers\get_user_timezone;

final readonly class UpdateProductData implements DataTransformer
{
    private function __construct(
        public ProductId $id,
        public StringLiteral $title,
        public StringLiteral $slug,
        public StringLiteral $body,
        public UserId $author,
        public StringLiteral $sku,
        public Money $price,
        public StringLiteral $purchaseUrl,
        public IntegerNumber $showInMenu,
        public IntegerNumber $showInSearch,
        public StringLiteral $featuredImage,
        public ArrayLiteral $meta,
        public StringLiteral $status,
        public DateTimeInterface $modified,
        public DateTimeInterface $modifiedGmt,
        public DateTimeInterface $published,
        public DateTimeInterface $publishedGmt,
    ) {
    }

    /**
     * @param DataValidator $data
     * @return DataTransformer
     * @throws TypeException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ReflectionException
     */
    public static function fromValidatedData(DataValidator $data): DataTransformer
    {
        $productPublished = new DateTime(
            time: $data->string(key: 'published')
        )->getDateTime();

        $productPublishedGmt = new DateTime(
            time: $data->string(key: 'publishedGmt'),
        )->getDateTime();

        $productModified = new DateTime(QubusDateTimeImmutable::now(get_user_timezone())->toDateTimeString())
            ->getDateTime();
        $productModifiedGmt = new DateTime(QubusDateTimeImmutable::now('GMT')->toDateTimeString())->getDateTime();

        $currencyCode = $data->string(key: 'currency');

        return new self(
            id: ProductId::fromString($data->string(key: 'id')),
            title: new StringLiteral(value: $data->string(key: 'title')),
            slug: new StringLiteral(value: $data->string(key: 'slug')),
            body: new StringLiteral(value: $data->string(key: 'body', default: '')),
            author: UserId::fromString(userId: $data->string(key: 'author')),
            sku: new StringLiteral(value: $data->string(key: 'sku')),
            price: new Money(new IntegerNumber($data->string(key: 'price')), new Currency(CurrencyCode::$currencyCode())),
            purchaseUrl: new StringLiteral(value: $data->string(key: 'purchaseUrl')),
            showInMenu: new IntegerNumber(value: $data->integer(key: 'showInMenu')),
            showInSearch: new IntegerNumber(value: $data->integer(key: 'showInSearch')),
            featuredImage: new StringLiteral(value: $data->string(key: 'featuredImage')),
            meta: new ArrayLiteral(data: $data->array(key: 'product_field', default: [])),
            status: new StringLiteral(value: $data->string(key: 'status')),
            modified: $productModified,
            modifiedGmt: $productModifiedGmt,
            published: $productPublished,
            publishedGmt: $productPublishedGmt,
        );
    }
}
