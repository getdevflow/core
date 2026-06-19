<?php

declare(strict_types=1);

namespace App\Domain\Content\Validator;

use App\Domain\Content\Dto\UpdateContentData;
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
use function App\Shared\Helpers\content_status_transition_allowed;
use function App\Shared\Helpers\get_content_by_id;
use function App\Shared\Helpers\current_user_can;

#[UseDto(UpdateContentData::class)]
class UpdateContentValidator extends HttpInputValidator implements HasDto
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
                false === current_user_can(perm: 'update:content')
        ) {
            return false;
        }

        $content = get_content_by_id((string) $this->all()['id']);

        if (empty($content->id)) {
            return false;
        }

        return content_status_transition_allowed(
            fromStatus: (string) $content->status,
            toStatus: (string) ($this->all()['status'] ?? 'draft'),
            publishedGmt: (string) (
                $this->all()['publishedGmt']
                ?? $this->all()['published']
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
            'sidebar' => 'nullable|int',
            'showInMenu' => 'nullable|int',
            'showInSearch' => 'nullable|int',
            'featuredImage' => 'nullable|string',
            'content_field' => 'nullable|array',
            'status' => 'required|string|in:' . $statuses,
            'published' => 'required|string',
        ];
    }
}
