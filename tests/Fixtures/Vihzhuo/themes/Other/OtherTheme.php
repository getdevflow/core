<?php

declare(strict_types=1);

namespace Theme\Other;

use Theme\Ancestor\AncestorTheme;

final class OtherTheme extends AncestorTheme
{
    public function meta(): array
    {
        return ['slug' => 'Other', 'basename' => 'Other', 'className' => self::class];
    }
}
