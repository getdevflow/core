<?php

declare(strict_types=1);

namespace App\Domain\ContentType\Validator;

use App\Domain\ContentType\Dto\DestroyContentTypeData;
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

use function App\Shared\Helpers\current_user_can;

#[UseDto(DestroyContentTypeData::class)]
class DestroyContentTypeValidator extends HttpInputValidator implements HasDto
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
        return current_user_can(perm: 'manage:content') && current_user_can(perm: 'delete:content');
    }

    /**
     * @return array<string, string>
     * @throws \Exception
     */
    public function rules(): array
    {
        return [
            'id' => 'required|ulid',
        ];
    }
}
