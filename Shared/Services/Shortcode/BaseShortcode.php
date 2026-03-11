<?php

declare(strict_types=1);

namespace App\Shared\Services\Shortcode;

abstract class BaseShortcode implements Shortcode
{
    protected array $defaultAttributes = [];

    public function render(array $attrs = [], string $content = null): string
    {
        $attrs = array_merge($this->defaultAttributes, $attrs);
        return $this->handle($attrs, $content);
    }

    abstract protected function handle(array $attrs, ?string $content): string;

    public function isSafe(): bool
    {
        return false; // default to unsafe (sanitized)
    }
}
