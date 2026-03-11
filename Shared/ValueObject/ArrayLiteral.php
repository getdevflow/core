<?php

declare(strict_types=1);

namespace App\Shared\ValueObject;

use JsonException;
use Qubus\ValueObjects\Util;
use Qubus\ValueObjects\ValueObject;

use function func_get_arg;
use function json_encode;

use const JSON_THROW_ON_ERROR;

class ArrayLiteral implements ValueObject
{
    private array $value = [];

    public function __construct(array $data = [])
    {
        $this->value = $data;
    }

    /**
     * Returns an array object.
     *
     */
    public static function fromNative(): self
    {
        $meta = func_get_arg(0);
        return new self($meta);
    }

    /**
     * Returns the value of the array.
     *
     * @return array
     */
    public function toNative(): array
    {
        return $this->value;
    }

    /**
     * Tells whether two arrays are equal by comparing their values.
     *
     * @param ArrayLiteral|ValueObject $object
     * @return bool
     */
    public function equals(ArrayLiteral|ValueObject $object): bool
    {
        if (false === Util::classEquals($this, $object)) {
            return false;
        }

        return $this->toNative() === $object->toNative();
    }

    /**
     * Tells whether the array is empty.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->value);
    }

    /**
     * Returns the array value itself.
     *
     * @throws JsonException
     */
    public function __toString(): string
    {
        return json_encode($this->value, JSON_THROW_ON_ERROR);
    }
}
