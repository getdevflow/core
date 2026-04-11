<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\User;

use JsonException;
use RuntimeException;

use const JSON_PRETTY_PRINT;

final class UserAttributeBag
{
    /**
     * @param array<string, mixed> $items
     */
    public function __construct(
        private readonly string $siteId,
        private readonly string $userId,
        private array $items = [],
    ) {
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function fromArray(string $siteId, string $userId, array $attributes): self
    {
        return new self($siteId, $userId, $attributes);
    }

    public static function empty(string $siteId, string $userId): self
    {
        return new self($siteId, $userId, []);
    }

    public static function fromJson(string $siteId, string $userId, ?string $json = null): self
    {
        if ($json === null || trim($json) === '') {
            return self::empty($siteId, $userId);
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Unable to decode site user attributes JSON.', 0, $e);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Site user attributes JSON must decode to an array/object.');
        }

        return new self($siteId, $userId, $decoded);
    }

    public function siteId(): string
    {
        return $this->siteId;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }

    public function getPath(string $path, mixed $default = null): mixed
    {
        $segments = explode('.', $path);
        $current = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    public function string(string $path, string $default = ''): string
    {
        $value = $this->getPath($path, $default);

        return is_string($value) ? $value : $default;
    }

    public function int(string $path, int $default = 0): int
    {
        $value = $this->getPath($path, $default);

        if (is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : $default;
    }

    public function bool(string $path, bool $default = false): bool
    {
        $value = $this->getPath($path, $default);

        return is_bool($value) ? $value : $default;
    }

    /**
     * @param array<mixed> $default
     * @return array<mixed>
     */
    public function array(string $path, array $default = []): array
    {
        $value = $this->getPath($path, $default);

        return is_array($value) ? $value : $default;
    }

    public function set(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->items[$key] = $value;

        return $clone;
    }

    public function setPath(string $path, mixed $value): self
    {
        $clone = clone $this;
        $segments = explode('.', $path);

        $current =& $clone->items;

        foreach ($segments as $index => $segment) {
            if ($index === array_key_last($segments)) {
                $current[$segment] = $value;
                break;
            }

            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }

            $current =& $current[$segment];
        }

        return $clone;
    }

    public function remove(string $key): self
    {
        $clone = clone $this;
        unset($clone->items[$key]);

        return $clone;
    }

    public function removePath(string $path): self
    {
        $clone = clone $this;
        $segments = explode('.', $path);
        $current =& $clone->items;

        foreach ($segments as $index => $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $clone;
            }

            if ($index === array_key_last($segments)) {
                unset($current[$segment]);

                return $clone;
            }

            $current =& $current[$segment];
        }

        return $clone;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function merge(array $values): self
    {
        $clone = clone $this;
        $clone->items = [...$clone->items, ...$values];

        return $clone;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function mergeRecursive(array $values): self
    {
        $clone = clone $this;
        $clone->items = $this->doMergeRecursive($clone->items, $values);

        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->items;
    }

    public function toJson(): string
    {
        try {
            return json_encode($this->items, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } catch (JsonException $e) {
            throw new RuntimeException('Unable to encode site user attribute JSON.', 0, $e);
        }
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     * @return array<string, mixed>
     */
    private function doMergeRecursive(array $left, array $right): array
    {
        foreach ($right as $key => $value) {
            if (
                    array_key_exists($key, $left)
                    && is_array($left[$key])
                    && is_array($value)
            ) {
                /** @var array<string, mixed> $leftChild */
                $leftChild = $left[$key];
                /** @var array<string, mixed> $rightChild */
                $rightChild = $value;

                $left[$key] = $this->doMergeRecursive($leftChild, $rightChild);
                continue;
            }

            $left[$key] = $value;
        }

        return $left;
    }
}
