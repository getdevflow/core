<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Dashboard;

use Closure;
use InvalidArgumentException;
use Throwable;

use function is_string;
use function ob_get_clean;
use function ob_start;
use function preg_match;
use function sprintf;

final class DashboardWidget
{
    public const string COLUMN_LEFT = 'left';
    public const string COLUMN_RIGHT = 'right';

    private string $description = '';
    private string $icon = 'fa fa-square';
    private string $column = self::COLUMN_LEFT;
    private int $priority = 10;
    private ?string $permission = null;
    private Closure $renderer;

    public function __construct(
        private readonly string $id,
        private readonly string $title,
        callable $renderer,
    ) {
        if (preg_match('/^[a-z0-9][a-z0-9._-]*$/', $id) !== 1) {
            throw new InvalidArgumentException(
                sprintf('Dashboard widget ID "%s" is invalid.', $id)
            );
        }

        if ($title === '') {
            throw new InvalidArgumentException('Dashboard widget title cannot be empty.');
        }

        $this->renderer = Closure::fromCallable($renderer);
    }

    public static function make(string $id, string $title, callable $renderer): self
    {
        return new self($id, $title, $renderer);
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function column(string $column): self
    {
        if (! in_array($column, [self::COLUMN_LEFT, self::COLUMN_RIGHT], true)) {
            throw new InvalidArgumentException(
                sprintf('Dashboard widget column "%s" is invalid.', $column)
            );
        }

        $this->column = $column;

        return $this;
    }

    public function priority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function permission(?string $permission): self
    {
        $this->permission = $permission;

        return $this;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getColumn(): string
    {
        return $this->column;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getPermission(): ?string
    {
        return $this->permission;
    }

    /**
     * A renderer may either return markup or print it directly.
     */
    public function render(): string
    {
        ob_start();

        try {
            $result = ($this->renderer)();
            $output = ob_get_clean();
        } catch (Throwable $exception) {
            ob_get_clean();
            throw $exception;
        }

        return ($output ?: '') . (is_string($result) ? $result : '');
    }
}
