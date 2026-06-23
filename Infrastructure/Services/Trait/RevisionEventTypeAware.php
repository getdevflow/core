<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Trait;

trait RevisionEventTypeAware
{
    private const array REVISION_EVENT_TYPES = [
        'content-was-created',
        'content-title-was-changed',
        'content-slug-was-changed',
        'content-body-was-changed',
    ];
}
