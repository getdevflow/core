<?php

declare(strict_types=1);

namespace App\Application;

use Qubus\Expressive\Database;
use Codefy\Framework\Proxy\Codefy;

class Devflow extends Codefy
{
    public static function db(): Database
    {
        return self::$PHP->make(name: Database::class);
    }

    public static function release(): string
    {
        return '2.2.3';
    }
}
