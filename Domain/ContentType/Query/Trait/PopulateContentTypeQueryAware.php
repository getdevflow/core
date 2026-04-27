<?php

declare(strict_types=1);

namespace App\Domain\ContentType\Query\Trait;

use Qubus\Exception\Exception;

use function Qubus\Security\Helpers\esc_html;

trait PopulateContentTypeQueryAware
{
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
            'id' => isset($data['content_type_id']) ? esc_html(string: $data['content_type_id']) : null,
            'title' => isset($data['content_type_title']) ? esc_html(string: $data['content_type_title']) : null,
            'slug' => isset($data['content_type_slug']) ? esc_html(string: $data['content_type_slug']) : null,
            'description' => isset($data['content_type_description']) ? esc_html(string: $data['content_type_description']) : null,
        ];
    }
}
