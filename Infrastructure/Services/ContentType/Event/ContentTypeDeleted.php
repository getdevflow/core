<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\ContentType\Event;

final readonly class ContentTypeDeleted
{
    /**
     * @param string $id
     */
    public function __construct(
        public string $id,
    ) {
    }
}
