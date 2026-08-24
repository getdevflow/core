<?php

declare(strict_types=1);

namespace App\Shared\Services;

use RuntimeException;

use function count;
use function file_get_contents;
use function is_array;
use function token_get_all;
use function trim;

use const T_CLASS;
use const T_DOUBLE_COLON;
use const T_ENUM;
use const T_INTERFACE;
use const T_NAME_QUALIFIED;
use const T_NEW;
use const T_NS_SEPARATOR;
use const T_STRING;
use const T_TRAIT;
use const T_WHITESPACE;

final class PhpFileParser
{
    /**
     * Get the full name (name \ namespace) of a class from its file path
     *
     * @param string $filePathName
     * @return string
     */
    public static function classFullNameFromFile(string $filePathName): string
    {
        $namespace = self::classNamespaceFromFile($filePathName);
        $class = self::classNameFromFile($filePathName);

        return $namespace === null ? $class : $namespace . '\\' . $class;
    }

    /**
     * Build and return an object of a class from its file path
     *
     * @param string $filePathName
     * @param mixed ...$args
     * @return mixed
     */
    public static function classObjectFromFile(string $filePathName, ...$args): mixed
    {
        $classString = self::classFullNameFromFile($filePathName);

        return new $classString(...$args);
    }

    /**
     * Get the class namespace from file path using token.
     *
     * @param string $filePathName
     * @return  null|string
     */
    protected static function classNamespaceFromFile(string $filePathName): ?string
    {
        if (! is_file($filePathName) || ! is_readable($filePathName)) {
            throw new RuntimeException(sprintf('Unable to read PHP file: %s', $filePathName));
        }

        $src = file_get_contents($filePathName);

        if ($src === false) {
            throw new RuntimeException(sprintf('Unable to read PHP file: %s', $filePathName));
        }

        $tokens = token_get_all($src);
        $count = count($tokens);
        $i = 0;
        $namespace = '';
        $namespace_ok = false;
        while ($i < $count) {
            $token = $tokens[$i];
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                // Found namespace declaration
                while (++$i < $count) {
                    if ($tokens[$i] === ';' || $tokens[$i] === '{') {
                        $namespace_ok = true;
                        $namespace = trim($namespace);
                        break;
                    }

                    if (
                        is_array($tokens[$i])
                        && in_array($tokens[$i][0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR, T_WHITESPACE], true)
                    ) {
                        $namespace .= $tokens[$i][1];
                    }
                }
                break;
            }
            $i++;
        }
        return $namespace_ok ? $namespace : null;
    }

    /**
     * get the class name form file path using token.
     *
     * @param string $filePathName
     * @return string
     */
    protected static function classNameFromFile(string $filePathName): string
    {
        if (! is_file($filePathName) || ! is_readable($filePathName)) {
            throw new RuntimeException(sprintf('Unable to read PHP file: %s', $filePathName));
        }

        $phpCode = file_get_contents($filePathName);

        if ($phpCode === false) {
            throw new RuntimeException(sprintf('Unable to read PHP file: %s', $filePathName));
        }

        $tokens = token_get_all($phpCode);
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (! is_array($token) || ! in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                continue;
            }

            if ($token[0] === T_CLASS) {
                $previous = self::previousMeaningfulToken($tokens, $i);

                if (is_array($previous) && in_array($previous[0], [T_NEW, T_DOUBLE_COLON], true)) {
                    continue;
                }
            }

            for ($j = $i + 1; $j < $count; $j++) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                    return $tokens[$j][1];
                }
            }
        }

        throw new RuntimeException(sprintf('No named PHP type found in file: %s', $filePathName));
    }

    private static function previousMeaningfulToken(array $tokens, int $offset): mixed
    {
        for ($i = $offset - 1; $i >= 0; $i--) {
            if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_WHITESPACE) {
                return $tokens[$i];
            }
        }

        return null;
    }
}
