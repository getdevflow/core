<?php

declare(strict_types=1);

namespace App\Domain\Content\Enum;

use function array_column;

enum ContentStatus: string
{
    case PUBLISHED = 'published';
    case SCHEDULED = 'scheduled';
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case ARCHIVED = 'archived';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(array: self::cases(), column_key: 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::PUBLISHED => 'Published',
            self::SCHEDULED => 'Scheduled',
            self::DRAFT => 'Draft',
            self::PENDING => 'Pending',
            self::ARCHIVED => 'Archived',
        };
    }

}
