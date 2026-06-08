<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Trait;

use App\Application\Devflow;

use function basename;
use function Codefy\Framework\Helpers\config;
use function parse_url;
use function rtrim;
use function strlen;
use function substr;
use function trim;

use const PHP_URL_PATH;

trait PathInfoAware
{
    /**
     * @return string
     * @throws \Qubus\Exception\Data\TypeException
     */
    public static function currentPath(): string
    {
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        $base = basename(config()->string(key: 'app.path'));

        if ($base !== '' && $base !== '/' && str_starts_with($requestUri, Devflow::$PHP::DS . $base . '/')) {
            $requestUri = substr($requestUri, strlen(Devflow::$PHP::DS . $base));
        }

        return '/' . trim($requestUri, '/');
    }

    /**
     * @param string|array $paths
     * @return bool
     * @throws \Qubus\Exception\Data\TypeException
     */
    public static function isPath(string|array $paths): bool
    {
        $current = rtrim(self::currentPath(), '/') . '/';

        foreach ((array) $paths as $path) {
            $path = rtrim($path, '/') . '/';

            if (str_starts_with($current, $path)) {
                return true;
            }
        }

        return false;
    }
}
