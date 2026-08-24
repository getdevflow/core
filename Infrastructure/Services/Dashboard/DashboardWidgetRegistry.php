<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Dashboard;

use Qubus\Inheritance\StaticProxyAware;

use function array_filter;
use function array_key_exists;
use function uasort;

final class DashboardWidgetRegistry
{
    use StaticProxyAware;

    /** @var array<string, DashboardWidget> */
    private array $widgets = [];

    public function register(string $id, string $title, callable $renderer): DashboardWidget
    {
        $widget = DashboardWidget::make($id, $title, $renderer);
        $this->widgets[$id] = $widget;

        return $widget;
    }

    public function add(DashboardWidget $widget): self
    {
        $this->widgets[$widget->id()] = $widget;

        return $this;
    }

    public function remove(string $id): self
    {
        unset($this->widgets[$id]);

        return $this;
    }

    public function get(string $id): ?DashboardWidget
    {
        return $this->widgets[$id] ?? null;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->widgets);
    }

    /**
     * @param callable(string): bool|null $can
     * @return array<string, DashboardWidget>
     */
    public function all(?callable $can = null): array
    {
        $widgets = $this->widgets;

        if ($can !== null) {
            $widgets = array_filter(
                $widgets,
                static fn (DashboardWidget $widget): bool =>
                    $widget->getPermission() === null || $can($widget->getPermission())
            );
        }

        uasort(
            $widgets,
            static fn (DashboardWidget $left, DashboardWidget $right): int =>
                [$left->getPriority(), $left->title()] <=> [$right->getPriority(), $right->title()]
        );

        return $widgets;
    }

    /**
     * Return a safe two-column layout containing only registered, authorized widgets.
     *
     * @param mixed $layout
     * @param callable(string): bool|null $can
     * @return array{left: list<string>, right: list<string>}
     */
    public function sanitizeLayout(mixed $layout, ?callable $can = null): array
    {
        $available = $this->all($can);
        $clean = [DashboardWidget::COLUMN_LEFT => [], DashboardWidget::COLUMN_RIGHT => []];
        $seen = [];

        if (! is_array($layout)) {
            return $clean;
        }

        foreach ([DashboardWidget::COLUMN_LEFT, DashboardWidget::COLUMN_RIGHT] as $column) {
            $ids = $layout[$column] ?? [];

            if (! is_array($ids)) {
                continue;
            }

            foreach ($ids as $id) {
                if (! is_string($id) || isset($seen[$id]) || ! isset($available[$id])) {
                    continue;
                }

                $clean[$column][] = $id;
                $seen[$id] = true;
            }
        }

        return $clean;
    }

    /**
     * @param callable(string): bool|null $can
     * @return array{left: list<string>, right: list<string>}
     */
    public function defaultLayout(?callable $can = null): array
    {
        $layout = [DashboardWidget::COLUMN_LEFT => [], DashboardWidget::COLUMN_RIGHT => []];

        foreach ($this->all($can) as $widget) {
            $layout[$widget->getColumn()][] = $widget->id();
        }

        return $layout;
    }

    /**
     * Resolve a stored layout, using defaults only when no preference has been saved.
     *
     * An empty stored layout is intentional: it means the user removed every widget.
     *
     * @param mixed $savedLayout
     * @param callable(string): bool|null $can
     * @return array{left: list<string>, right: list<string>}
     */
    public function resolveLayout(mixed $savedLayout, ?callable $can = null): array
    {
        if ($savedLayout === null) {
            return $this->defaultLayout($can);
        }

        return $this->sanitizeLayout($savedLayout, $can);
    }

    /**
     * @param array{left: list<string>, right: list<string>} $layout
     * @param callable(string): bool|null $can
     * @return array<string, DashboardWidget>
     */
    public function inactive(array $layout, ?callable $can = null): array
    {
        $active = [...$layout[DashboardWidget::COLUMN_LEFT], ...$layout[DashboardWidget::COLUMN_RIGHT]];

        return array_filter(
            $this->all($can),
            static fn (DashboardWidget $widget): bool => ! in_array($widget->id(), $active, true)
        );
    }

    public function clear(): void
    {
        $this->widgets = [];
    }
}
