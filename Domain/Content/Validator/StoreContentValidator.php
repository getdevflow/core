<?php

declare(strict_types=1);

namespace App\Domain\Content\Validator;

use App\Domain\Content\Dto\StoreContentData;
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

use function App\Shared\Helpers\content_status_capabilities;
use function App\Shared\Helpers\content_status_create_allowed;
use function App\Shared\Helpers\current_user_can;

#[UseDto(StoreContentData::class)]
class StoreContentValidator extends HttpInputValidator implements HasDto
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
                false === current_user_can(perm: 'manage:content') ||
                false === current_user_can(perm: 'create:content')
        ) {
            return false;
        }

        return content_status_create_allowed(
            toStatus: (string) ($this->all()['status'] ?? 'draft'),
            publishedGmt: (string) (
                $this->all()['published']
                ?? $this->all()['publishedGmt']
                ?? ''
            )
        );
    }

    /**
     * @return array<string, string>
     * @throws \Exception
     */
    public function rules(): array
    {
        $statuses = implode(separator: ',', array: array_keys(content_status_capabilities()));

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
