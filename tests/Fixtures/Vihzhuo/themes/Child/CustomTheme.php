<?php

declare(strict_types=1);

namespace Theme\Child;

use Theme\ParentTheme\ParentTheme;

final class CustomTheme extends ParentTheme
{
    public function meta(): array
    {
        return ['slug' => 'Child', 'basename' => 'Child', 'className' => self::class];
    }
}
