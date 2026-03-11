<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject;

use Qubus\Exception\Data\TypeException;
use Qubus\Support\Assertion;
use Qubus\ValueObjects\StringLiteral\StringLiteral;

use function App\Shared\Helpers\get_user_roles;

class UserRole extends StringLiteral
{
    /**
     * @throws TypeException
     */
    public function __construct(string $value)
    {
        Assertion::inArray(
            value: $value,
            values: get_user_roles(),
            message: sprintf(
                'User role must be one of the following: %s',
                implode(', ', get_user_roles())
            )
        );

        parent::__construct($value);
    }
}
