<?php

declare(strict_types=1);

namespace App\Domain\User\Validator;

use App\Domain\User\Dto\UpdateUserProfileData;
use Codefy\Framework\Dto\Attribute\UseDto;
use Codefy\Framework\Dto\HasDto;
use Codefy\Framework\Dto\Trait\DtoAware;
use Codefy\Framework\Validation\HttpInputValidator;

use function App\Shared\Helpers\current_user_can;
use function array_values;
use function Codefy\Framework\Helpers\config;
use function Codefy\Framework\Helpers\get_system_roles;
use function implode;

#[UseDto(UpdateUserProfileData::class)]
class UpdateUserProfileValidator extends HttpInputValidator implements HasDto
{
    use DtoAware;

    /**
     * @return bool
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Qubus\Exception\Data\TypeException
     * @throws \Qubus\Exception\Exception
     * @throws \ReflectionException
     */
    public function authorize(): bool
    {
        if (false === current_user_can(perm: 'manage:profile')) {
            return false;
        }

        return true;
    }

    /**
     * @return string[]
     * @throws \Qubus\Exception\Exception
     */
    public function rules(): array
    {
        $roles = implode(separator: ',', array: array_values(get_system_roles()));
        $statuses = 'A,I,S,B';

        return [
            'id' => 'required|ulid',
            'fname' => 'required|string|min:3',
            'mname' => 'nullable|string',
            'lname' => 'required|string|min:3',
            'email' => 'required|email',
            'login' => 'required|string|min:' . config()->integer(key: 'auth.username_min_length'),
            'url' => 'nullable|string',
            'bio'  => 'nullable|string',
            'user_field' => 'nullable|array',
            'status'   => 'required|string|in:' . $statuses,
            'role'  => 'required|string|in:' . $roles,
            'timezone' => 'nullable|string',
            'date_format' => 'nullable|string',
            'time_format' => 'nullable|string',
            'adminLayout' => 'int',
            'adminSidebar' => 'int',
            'adminSkin' => 'int',
        ];
    }
}
