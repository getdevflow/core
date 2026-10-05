<?php

declare(strict_types=1);

namespace Theme\ParentTheme;

use Theme\Ancestor\AncestorTheme;

class ParentTheme extends AncestorTheme
{
    public function meta(): array
    {
        return ['slug' => 'ParentTheme', 'basename' => 'ParentTheme', 'className' => self::class];
    }
}
