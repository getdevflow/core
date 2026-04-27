<?php

declare(strict_types=1);

namespace App\Domain\User\Enum;

use function Qubus\Security\Helpers\t__;

enum UserStatus: string
{
    case A = 'Active';
    case I = 'Inactive';
    case B = 'Blocked';
    case S = 'Spammer';

    public function label(): string
    {
        return match ($this) {
            self::A => t__(msgid: 'Active', domain: 'devflow'),
            self::I => t__(msgid: 'Inactive', domain: 'devflow'),
            self::B => t__(msgid: 'Blocked', domain: 'devflow'),
            self::S => t__(msgid: 'Spammer', domain: 'devflow'),
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
