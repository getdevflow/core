<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Trait;

use Qubus\Exception\Exception;

use function Qubus\Security\Helpers\esc_html;

trait CleanAware
{
    /**
     * @throws Exception
     */
    private function clean(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return esc_html((string) $value);
    }
}
