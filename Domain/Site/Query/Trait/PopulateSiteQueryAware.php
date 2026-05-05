<?php

declare(strict_types=1);

namespace App\Domain\Site\Query\Trait;

use App\Infrastructure\Services\Trait\CleanAware;
use Qubus\Exception\Exception;

trait PopulateSiteQueryAware
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
            'id' => $this->clean($data['site_id']),
            'key' => $this->clean($data['site_key']),
            'name' => $this->clean($data['site_name']),
            'slug' => $this->clean($data['site_slug']),
            'domain' => $this->clean($data['site_domain']),
            'mapping' => $this->clean($data['site_mapping']),
            'path' => $this->clean($data['site_path']),
            'owner' => $this->clean($data['site_owner']),
            'status' => $this->clean($data['site_status']),
            'registered' => $this->clean($data['site_registered']),
            'modified' => $this->clean($data['site_modified']),
        ];
    }
}
