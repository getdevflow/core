<?php

declare(strict_types=1);

namespace App\Domain\ContentType\Validator;

use App\Domain\ContentType\Dto\UpdateContentTypeData;
use Codefy\CommandBus\Exceptions\CommandPropertyNotFoundException;
use Codefy\Framework\Dto\Attribute\UseDto;
use Codefy\Framework\Dto\HasDto;
use Codefy\Framework\Dto\Trait\DtoAware;
use Codefy\Framework\Validation\HttpInputValidator;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;

use function App\Shared\Helpers\current_user_can;

#[UseDto(UpdateContentTypeData::class)]
class UpdateContentTypeValidator extends HttpInputValidator implements HasDto
{
    use DtoAware;

    /**
     * @throws NotFoundExceptionInterface
     * @throws UnresolvableQueryHandlerException
     * @throws ContainerExceptionInterface
     * @throws CommandPropertyNotFoundException
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ReflectionException
     * @throws TypeException
     */
    public function authorize(): bool
    {
        return current_user_can(perm: 'manage:content') && current_user_can(perm: 'update:content');
    }

    /**
     * @return array<string, string>
     * @throws \Exception
     */
    public function rules(): array
    {
        return [
            'id' => 'required|ulid',
            'title' => 'required|string|min:3',
            'slug' => 'required|string|min:3',
            'description' => 'string',
        ];
    }
}
