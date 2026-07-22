<?php

declare(strict_types=1);

namespace App\Domain\User\Validator;

use App\Domain\User\Dto\StoreUserData;
use Codefy\Framework\Dto\Attribute\UseDto;
use Codefy\Framework\Dto\HasDto;
use Codefy\Framework\Dto\Trait\DtoAware;
use Codefy\Framework\Validation\HttpInputValidator;

use function App\Shared\Helpers\current_user_can;
use function array_values;
use function Codefy\Framework\Helpers\config;
use function Codefy\Framework\Helpers\get_system_roles;
use function implode;

#[UseDto(StoreUserData::class)]
class StoreUserValidator extends HttpInputValidator implements HasDto
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
        if (false === current_user_can(perm: 'create:users')) {
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

        $login = 'required|string|min:' . config()->integer(key: 'auth.username_min_length');
        if (!isset($this->all()['login'])) {
            $login = 'nullable|string';
        }

        $id = 'required|ulid';
        if (!isset($this->all()['id'])) {
            $id = 'nullable|string';
        }

        return [
            'id' => $id,
            'fname' => 'required|string|min:3',
            'mname' => 'nullable|string',
            'lname' => 'required|string|min:3',
            'email' => 'required|email',
            'login' => $login,
            'pass'  => 'required|string|min:' . config()->integer(key: 'auth.password_min_length'),
            'user_field' => 'nullable|array',
            'status'   => 'required|string|in:' . $statuses,
            'role'  => 'required|string|in:' . $roles,
            'sendemail' => 'int',
        ];
    }
}
