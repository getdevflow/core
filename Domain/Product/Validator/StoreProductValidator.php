<?php

declare(strict_types=1);

namespace App\Domain\Product\Validator;

use App\Domain\Product\Dto\StoreProductData;
use App\Domain\Product\Enum\ProductStatus;
use Codefy\Framework\Dto\Attribute\UseDto;
use Codefy\Framework\Dto\HasDto;
use Codefy\Framework\Dto\Trait\DtoAware;
use Codefy\Framework\Validation\HttpInputValidator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;

use function App\Shared\Helpers\user_can_set_product_status;
use function App\Shared\Helpers\current_user_can;

#[UseDto(StoreProductData::class)]
class StoreProductValidator extends HttpInputValidator implements HasDto
{
    use DtoAware;

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ReflectionException
     * @throws TypeException
     */
    public function authorize(): bool
    {
        if (
                false === current_user_can(perm: 'manage:products') ||
                false === current_user_can(perm: 'create:product')
        ) {
            return false;
        }

        return user_can_set_product_status(
            status: (string) ($this->all()['status'] ?? 'draft')
        );
    }

    /**
     * @return array<string, string>
     * @throws \Exception
     */
    public function rules(): array
    {
        $statuses = implode(separator: ',', array: ProductStatus::values());

        return [
            'id' => 'required|ulid',
            'title' => 'required|string|min:3',
            'slug' => 'required|string|min:3',
            'body' => 'string',
            'author' => 'required|ulid',
            'sku' => 'required|string',
            'price' => 'required',
            'purchaseUrl' => 'nullable|string',
            'showInMenu' => 'int',
            'showInSearch' => 'int',
            'featuredImage' => 'string',
            'product_field' => 'nullable|array',
            'status' => 'required|string|in:' . $statuses,
            'published' => 'required|string',
        ];
    }
}
