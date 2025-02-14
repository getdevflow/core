<?php

declare(strict_types=1);

namespace App\Shared\Services;

use function Qubus\Support\Helpers\convert_array_to_object;

final class TemplateRegistry
{
    /**
     * Holds the registry data
     *
     * @var array
     */
    private static array $data = [];

    /**
     * Retrieves a sub-key from the registry.
     *
     * @param string      $object  Name of an object to retrieve a key from.
     * @param string|bool $key     (Optional) key to retrieve from the object.
     * @param mixed|null  $default (Optional) default value for missing objects or keys.
     *
     * @return mixed|null
     */
    public static function prop(string $object, string|bool $key = false, mixed $default = null): mixed
    {
        if ($obj = self::get($object)) {
            if (is_array($obj)) {
                $obj = convert_array_to_object($obj);
            }
            return ($key !== false ? $obj->{$key} : $obj);
        }

        return $default;
    }

    /**
     * Retrieves a key from the registry.
     *
     * @param string     $key     Name of the key to retrieve
     * @param mixed|null $default (Optional) fallback value for missing keys.
     * @return mixed|null Key value if found, fallback if given, or null otherwise.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (isset(self::$data[$key])) {
            return self::$data[$key];
        }

        return $default;
    }

    /**
     * Sets a value to the registry.
     *
     * @param string $key   Name of the key to set.
     * @param mixed  $value Value to set.
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        self::$data[$key] = $value;
    }

    /**
     * Checks whether the registry has a key.
     *
     * @param string $key Name of the key to check for.
     * @return bool
     */
    public static function has(string $key): bool
    {
        return isset(self::$data[$key]);
    }
}
