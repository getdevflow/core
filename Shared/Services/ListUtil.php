<?php

declare(strict_types=1);

namespace App\Shared\Services;

use Qubus\Exception\Data\TypeException;
use Stringable;

use function array_key_exists;
use function count;
use function in_array;
use function is_numeric;
use function is_object;
use function strtoupper;
use function uasort;
use function usort;

final class ListUtil
{
    /**
     * @var array<array-key, array<string|int, mixed>|object>
     */
    private array $input = [] {
        &get => $this->input;
    }

    /**
     * @var array<array-key, mixed>
     */
    private array $output = [] {
        &get => $this->output;
    }

    /**
     * @param array<array-key, array<string|int, mixed>|object> $input
     */
    public function __construct(array $input)
    {
        $this->output = $this->input = $input;
    }

    /**
     * Filters the list, based on a set of key => value arguments.
     *
     * @param array $args Optional. An array of key => value arguments to match
     *                         against each object. Default empty array.
     * @param string $operator Optional. The logical operation to perform. 'AND' means
     *                         all elements from the array must match. 'OR' means only
     *                         one element needs to match. 'NOT' means no elements may
     *                         match. Default 'AND'.
     * @return array Array of found values.
     * @throws TypeException
     */
    public function filter(array $args = [], string $operator = 'AND'): array
    {
        if ($args === []) {
            return $this->output;
        }

        $operator = strtoupper($operator);

        if (!in_array($operator, ['AND', 'OR', 'NOT'], true)) {
            throw new TypeException(
                'Filter operator must be one of: AND, OR, NOT.'
            );
        }

        $requiredMatches = count($args);
        $filtered = [];

        foreach ($this->output as $key => $item) {
            $matched = 0;

            foreach ($args as $field => $expectedValue) {
                $actualValue = $this->value($item, $field);

                if ($actualValue->exists && $actualValue->value === $expectedValue) {
                    $matched++;
                }
            }

            $keep = match ($operator) {
                'AND' => $matched === $requiredMatches,
                'OR' => $matched > 0,
                'NOT' => $matched === 0,
            };

            if ($keep) {
                $filtered[$key] = $item;
            }
        }

        return $this->output = $filtered;
    }

    /**
     * Plucks a certain field out of each object in the list.
     *
     * This has the same functionality and prototype of
     * array_column() but also supports objects.
     *
     * @param int|string $field Field from the object to place instead of the entire object
     * @param int|string|null $indexKey Optional. Field from the object to use as keys for the new array.
     *                                  Default null.
     * @return array Array of found values. If `$indexKey` is set, an array of found values with keys
     *               corresponding to `$indexKey`. If `$indexKey` is null, array keys from the original
     *               `$list` will be preserved in the results.
     */
    public function pluck(int|string $field, int|string|null $indexKey = null): array
    {
        $newList = [];

        foreach ($this->output as $originalKey => $item) {
            $fieldValue = $this->value($item, $field);

            if (!$fieldValue->exists) {
                continue;
            }

            if ($indexKey === null) {
                $newList[$originalKey] = $fieldValue->value;
                continue;
            }

            $indexValue = $this->value($item, $indexKey);

            if ($indexValue->exists && $this->isValidArrayKey($indexValue->value)) {
                $newList[$indexValue->value] = $fieldValue->value;
                continue;
            }

            $newList[] = $fieldValue->value;
        }

        return $this->output = $newList;
    }

    /**
     * Sorts the list, based on one or more orderby arguments.
     *
     * @param array|string $orderby Optional. Either the field name to order by or an array
     *                              of multiple orderby fields as $orderby => $order.
     * @param string $order         Optional. Either 'ASC' or 'DESC'. Only used if $orderby
     *                              is a string.
     * @param bool $preserveKeys    Optional. Whether to preserve keys. Default false.
     * @return array The sorted array.
     */
    public function sort(array|string $orderby = [], string $order = 'ASC', bool $preserveKeys = false): array
    {
        if ($orderby === [] || $orderby === '') {
            return $this->output;
        }

        $orderBy = is_string($orderby)
            ? [$orderby => $order]
            : $orderby;

        foreach ($orderBy as $field => $direction) {
            $orderBy[$field] = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        }

        $callback = function (mixed $a, mixed $b) use ($orderBy): int {
            return $this->compareItems($a, $b, $orderBy);
        };

        if ($preserveKeys) {
            uasort($this->output, $callback);
        } else {
            usort($this->output, $callback);
        }

        return $this->output;
    }

    /**
     * Reset output back to original input.
     *
     * @return array<array-key, array<string|int, mixed>|object>
     */
    public function reset(): array
    {
        return $this->output = $this->input;
    }

    /**
     * Return the current working list.
     *
     * @return array<array-key, mixed>
     */
    public function all(): array
    {
        return $this->output;
    }

    /**
     * @param array<string, string> $orderBy
     */
    private function compareItems(mixed $a, mixed $b, array $orderBy): int
    {
        foreach ($orderBy as $field => $direction) {
            $aValue = $this->value($a, $field);
            $bValue = $this->value($b, $field);

            if (!$aValue->exists || !$bValue->exists) {
                continue;
            }

            $comparison = $this->compareValues($aValue->value, $bValue->value);

            if ($comparison === 0) {
                continue;
            }

            return $direction === 'DESC' ? -$comparison : $comparison;
        }

        return 0;
    }

    private function compareValues(mixed $a, mixed $b): int
    {
        if ($a === $b) {
            return 0;
        }

        if ((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))) {
            return $a <=> $b;
        }

        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a <=> (float) $b;
        }

        if ($a instanceof Stringable) {
            $a = (string) $a;
        }

        if ($b instanceof Stringable) {
            $b = (string) $b;
        }

        if (is_scalar($a) && is_scalar($b)) {
            return strcasecmp((string) $a, (string) $b);
        }

        return 0;
    }

    private function value(mixed $item, int|string $field): FieldValue
    {
        if (is_array($item) && array_key_exists($field, $item)) {
            return new FieldValue(true, $item[$field]);
        }

        if (is_object($item) && isset($item->{$field})) {
            return new FieldValue(true, $item->{$field});
        }

        return new FieldValue(false, null);
    }

    private function isValidArrayKey(mixed $value): bool
    {
        return is_int($value) || is_string($value);
    }
}
