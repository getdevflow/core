<?php

declare(strict_types=1);

namespace App\Shared\Services\Elfinder;

class Elfinder
{
    public static function checkAccess($attr, $path, $data, $volume): ?bool
    {
        return strpos(basename($path), '.') === 0       // if file/folder begins with '.' (dot)
        ? !($attr === 'read' || $attr === 'write')    // set read+write to false, other (locked+hidden) set to true
        :  null;                                    // else elFinder decide it itself
    }
}
