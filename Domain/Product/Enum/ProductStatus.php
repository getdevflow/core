<?php

declare(strict_types=1);

namespace App\Domain\Product\Enum;

use function array_column;
use function Codefy\Framework\Helpers\trans;

enum ProductStatus: string
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
            self::PUBLISHED => trans('Published'),
            self::SCHEDULED => trans('Scheduled'),
            self::DRAFT => trans('Draft'),
            self::PENDING => trans('Pending'),
            self::ARCHIVED => trans('Archived'),
        };
    }

}
