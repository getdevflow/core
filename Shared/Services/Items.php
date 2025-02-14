<?php

declare(strict_types=1);

namespace App\Shared\Services;

use Iterator;

final class Items implements Iterator
{
    private int $position = 0;

    private array $items = [];

    /**
     * Items constructor
     *
     * @param array $items (optional) items to iterate on
     */
    public function __construct(array $items = [])
    {
        $this->position = 0;
        $this->items    = $items;
    }

    /**
     * @inheritDoc
     */
    public function current(): mixed
    {
        return $this->items[$this->position];
    }

    /**
     * @inheritDoc
     */
    public function next(): void
    {
        ++$this->position;
    }

    /**
     * @inheritDoc
     */
    public function key(): int
    {
        return $this->position;
    }

    /**
     * @inheritDoc
     */
    public function valid(): bool
    {
        return isset($this->items[$this->position]);
    }

    /**
     * @inheritDoc
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * Retrieves the length
     *
     * @return int
     */
    public function length(): int
    {
        return count($this->items);
    }
}
