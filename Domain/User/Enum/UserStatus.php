<?php

declare(strict_types=1);

namespace App\Domain\User\Enum;

use function Codefy\Framework\Helpers\trans;

enum UserStatus: string
{
    case A = 'Active';
    case I = 'Inactive';
    case B = 'Blocked';
    case S = 'Spammer';

    public function label(): string
    {
        return match ($this) {
            self::A => trans('Active'),
            self::I => trans('Inactive'),
            self::B => trans('Blocked'),
            self::S => trans('Spammer'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::A => 'label-success',
            self::I => 'label-warning',
            self::B => 'label-default',
            self::S => 'label-danger',
        };
    }
}
