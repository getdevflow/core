<?php

declare(strict_types=1);

namespace App\Shared\Services;

use Codefy\Framework\Codefy;
use Codefy\Framework\Support\ArgsParser;
use Codefy\Framework\Support\StringParser;
use Qubus\EventDispatcher\ActionFilter\Filter;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;

use function basename;
use function Codefy\Framework\Helpers\config;
use function ctype_digit;
use function strpos;

class Utils
{
    /**
     * Parses a string into variables to be stored in an array.
     *
     * @param string $string The string to be parsed.
     * @param array  $array  Variables will be stored in this array.
     * @param bool   $strict If true, skip malformed entries.
     * @return array
     * @throws Exception
     * @throws ReflectionException
     */
    public static function parseStr(string $string, array $array, bool $strict = false): array
    {
        $array = StringParser::parse($string, $array, $strict);
        /**
         * Filter the array of variables derived from a parsed string.
         *
         * @param array $array The array populated with variables.
         */
        return Filter::getInstance()->applyFilter('parse_str', $array);
    }

    /**
     * Checks if a variable is null. If not null, check if integer or string.
     *
     * @param int|string $var  Variable to check.
     * @return string|int|null Returns null if empty otherwise a string or an integer.
     */
    public static function ifNull(int|string $var): int|string|null
    {
        $var = ctype_digit($var) ? (int) $var : (string) $var;
        return $var === '' ? null : $var;
    }

    /**
     * Merge user defined arguments into defaults array.
     *
     * @param array|string|object $args     Value to merge with $defaults.
     * @param array|string        $defaults Optional. Array that serves as the defaults. Default empty.
     * @param bool                $deep     Whether to deep merge nested arrays.
     * @return array Merged user defined values with defaults.
     */
    public static function parseArgs(array|string|object $args, array|string $defaults = '', bool $deep = false): array
    {
        return ArgsParser::parse($args, $defaults, $deep);
    }

    /**
     * @throws TypeException
     */
    public static function getPathInfo(string $relative)
    {
        $base = basename(config(key: 'app.path'));
        if (str_starts_with($_SERVER['REQUEST_URI'], Codefy::$PHP::DS . $base . $relative)) {
            return $relative;
        } else {
            return $_SERVER['REQUEST_URI'];
        }
    }

    /**
     * Whether the current request is for an administrative interface.
     *
     * e.g. `/admin/`
     *
     * @return bool True if an admin screen, otherwise false.
     * @throws TypeException
     */
    public static function isAdmin(): bool
    {
        if (str_starts_with(self::getPathInfo('/admin'), "/admin")) {
            return true;
        }
        return false;
    }

    /**
     * Whether the current request is for a login interface.
     *
     * e.g. `/login/`
     *
     * @return bool True if login screen, otherwise false.
     * @throws TypeException
     */
    public static function isLogin(): bool
    {
        if (str_starts_with(self::getPathInfo('/login'), "/login")) {
            return true;
        }
        return false;
    }
}
