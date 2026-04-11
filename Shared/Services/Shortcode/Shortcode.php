<?php

declare(strict_types=1);

namespace App\Shared\Services\Shortcode;

interface Shortcode
{
    public function tag(): string;

    public function render(array $attrs = [], ?string $content = null): string;

    public function isSafe(): bool;
}
