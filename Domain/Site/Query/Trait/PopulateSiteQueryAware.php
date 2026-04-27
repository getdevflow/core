<?php

declare(strict_types=1);

namespace App\Domain\Site\Query\Trait;

use Qubus\Exception\Exception;

use function Qubus\Security\Helpers\esc_html;

trait PopulateSiteQueryAware
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
            'id' => isset($data['site_id']) ? esc_html(string: $data['site_id']) : null,
            'key' => isset($data['site_key']) ? esc_html(string: $data['site_key']) : null,
            'name' => isset($data['site_name']) ? esc_html(string: $data['site_name']) : null,
            'slug' => isset($data['site_slug']) ? esc_html(string: $data['site_slug']) : null,
            'domain' => isset($data['site_domain']) ? esc_html(string: $data['site_domain']) : null,
            'mapping' => isset($data['site_mapping']) ? esc_html(string: $data['site_mapping']) : null,
            'path' => isset($data['site_path']) ? esc_html(string: $data['site_path']) : null,
            'owner' => isset($data['site_owner']) ? esc_html(string: $data['site_owner']) : null,
            'status' => isset($data['site_status']) ? esc_html(string: $data['site_status']) : null,
            'registered' => isset($data['site_registered']) ? esc_html(string: $data['site_registered']) : null,
            'modified' => isset($data['site_modified']) ? esc_html(string: $data['site_modified']) : null,
        ];
    }
}
