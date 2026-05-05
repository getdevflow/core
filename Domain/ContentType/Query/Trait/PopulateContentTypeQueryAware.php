<?php

declare(strict_types=1);

namespace App\Domain\ContentType\Query\Trait;

use App\Infrastructure\Services\Trait\CleanAware;
use Qubus\Exception\Exception;

trait PopulateContentTypeQueryAware
{
    use CleanAware;
    
    /**
     * Populate an array of values from result query.
     *
     * @param array|null $data
     * @return array|null
     * @throws Exception
     */
    private function populate(?array $data = []): ?array
    {
        return [
            'id' => $this->clean($data['content_type_id']),
            'title' => $this->clean($data['content_type_title']),
            'slug' => $this->clean($data['content_type_slug']),
            'description' => $this->clean($data['content_type_description']),
        ];
    }
}
