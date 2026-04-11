<?php

declare(strict_types=1);

namespace App\Domain\Content\Validator;

use App\Domain\Content\Dto\StoreContentData;
use App\Domain\Content\Enum\ContentStatus;
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

#[UseDto(StoreContentData::class)]
class StoreContentValidator extends HttpInputValidator implements HasDto
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
        return current_user_can(perm: 'manage:content') && current_user_can(perm: 'create:content');
    }

    /**
     * @return array<string, string>
     * @throws \Exception
     */
    public function rules(): array
    {
        $statuses = implode(separator: ',', array: ContentStatus::values());

        if('NULL' === $this->all()['parent']) {
            $parent = 'nullable|string';
        } else {
            $parent = 'required|ulid';
        }

        return [
            'id' => 'required|ulid',
            'title' => 'required|string|min:3',
            'slug' => 'required|string|min:3',
            'body' => 'string',
            'author' => 'required|ulid',
            'type' => 'required|string',
            'parent' => $parent,
            'sidebar' => 'int',
            'showInMenu' => 'int',
            'showInSearch' => 'int',
            'featuredImage' => 'string',
            'content_field' => 'nullable|array',
            'status' => 'required|string|in:' . $statuses,
            'published' => 'required|string',
        ];
    }
}
