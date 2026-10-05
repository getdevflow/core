<?php

declare(strict_types=1);

namespace Theme\Ancestor;

use App\Infrastructure\Services\Theme;

class AncestorTheme extends Theme
{
    public function meta(): array
    {
        return ['slug' => 'Ancestor', 'basename' => 'Ancestor', 'className' => self::class];
    }

    public function handle(): void
    {
    }
}
