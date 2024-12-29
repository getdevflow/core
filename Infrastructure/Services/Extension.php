<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

interface Extension
{
    /**
     * Extension meta data.
     */
    public function meta(): array;

    /**
     * Handle method to execute when extension is activated.
     *
     * @return void
     */
    public function handle(): void;
}
