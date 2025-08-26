<?php

declare(strict_types=1);

namespace App\Application;

use App\Infrastructure\Persistence\Database;
use Codefy\Framework\Codefy;
use Qubus\Exception\Exception;
use Qubus\Expressive\QueryBuilder;

final class Devflow extends Codefy
{
    public static function db(): Database
    {
        return self::$PHP->make(name: Database::class);
    }

    /**
     * @throws Exception
     */
    public static function query(): QueryBuilder
    {
        return self::$PHP->getDb();
    }

    public static function release(): string
    {
        return '2.0.0-beta.2';
    }
}
