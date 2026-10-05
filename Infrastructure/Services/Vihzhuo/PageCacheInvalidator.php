<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Vihzhuo;

use FilesystemIterator;
use Qubus\Exception\Data\TypeException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Vihzhuo\Cache;
use Vihzhuo\Contracts\CacheContract;

use function Codefy\Framework\Helpers\config;

final class PageCacheInvalidator
{
    /**
     * Clear Vihzhuo's existing page cache after theme changes or the CMS cache flush.
     *
     * @throws TypeException
     */
    public static function clear(): void
    {
        $config = config()->array('vihzhuo');
        if ($config === [] || ($config['cache']['enabled'] ?? false) !== true) {
            return;
        }

        $previousConfig = $GLOBALS['phpb_config'] ?? null;
        try {
            $GLOBALS['phpb_config'] = $config;
            $cache = phpb_instance('cache') ?? new Cache();
            if (!$cache instanceof CacheContract) {
                return;
            }
            $cache->invalidate('*');

            // The built-in cache deliberately refuses to remove its root, which may
            // also contain other CMS caches. Invalidate its recorded page URLs instead.
            $folder = $config['cache']['folder'] ?? '';
            if (!$cache instanceof Cache || !is_string($folder) || !is_dir($folder)) {
                return;
            }
            $urls = [];
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($files as $file) {
                if ($file->isFile() && !$file->isLink() && $file->getFilename() === 'url.txt') {
                    $url = file_get_contents($file->getPathname());
                    if (is_string($url) && $url !== '') {
                        $urls[$url] = true;
                    }
                }
            }
            foreach (array_keys($urls) as $url) {
                $cache->invalidate($url);
            }
        } finally {
            if ($previousConfig === null) {
                unset($GLOBALS['phpb_config']);
            } else {
                $GLOBALS['phpb_config'] = $previousConfig;
            }
        }
    }
}
