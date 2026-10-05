<?php

declare(strict_types=1);

namespace Theme\ParentTheme\Blocks\Php\Inherited;

use Vihzhuo\Modules\GrapesJS\Block\BaseModel;

final class Model extends BaseModel
{
    public function label(): string
    {
        return 'Inherited model';
    }
}
