<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Site\Query\Trait\PopulateSiteQueryAware;
use App\Domain\Site\Repository\SitesQueryRepository;
use App\Infrastructure\Persistence\Database;
use Qubus\Exception\Exception;
use ReflectionException;

use function Qubus\Support\Helpers\convert_array_to_object;
use function Qubus\Support\Helpers\is_null__;

class QueryBusSitesRepository implements SitesQueryRepository
{
    use PopulateSiteQueryAware;

    public function __construct(protected Database $dfdb)
    {
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function findById(string $siteId): array|object
    {
        $sql = "SELECT * FROM {$this->dfdb->basePrefix}site WHERE site_id = ?";

        $data = $this->dfdb->getResults($this->dfdb->prepare($sql, [$siteId]), Database::ARRAY_A);

        $contents = [];

        if (!is_null__($data)) {
            foreach ($data as $content) {
                $contents[] = $this->populate($content);
            }
        }

        return convert_array_to_object($contents);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function findByKey(string $siteKey): array|object
    {
        $sql = "SELECT * FROM {$this->dfdb->basePrefix}site WHERE site_key = ?";

        $data = $this->dfdb->getResults($this->dfdb->prepare($sql, [$siteKey]), Database::ARRAY_A);

        $contents = [];

        if (!is_null__($data)) {
            foreach ($data as $content) {
                $contents[] = $this->populate($content);
            }
        }

        return convert_array_to_object($contents);
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    public function findBySlug(string $siteSlug): array|object
    {
        $sql = "SELECT * FROM {$this->dfdb->basePrefix}site WHERE site_slug = ?";

        $data = $this->dfdb->getResults($this->dfdb->prepare($sql, [$siteSlug]), Database::ARRAY_A);

        $contents = [];

        if (!is_null__($data)) {
            foreach ($data as $content) {
                $contents[] = $this->populate($content);
            }
        }

        return convert_array_to_object($contents);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function findByOwner(string $siteOwner): array|object
    {
        $sql = "SELECT * FROM {$this->dfdb->basePrefix}site WHERE site_owner = ?";

        $data = $this->dfdb->getResults($this->dfdb->prepare($sql, [$siteOwner]), Database::ARRAY_A);

        $contents = [];

        if (!is_null__($data)) {
            foreach ($data as $content) {
                $contents[] = $this->populate($content);
            }
        }

        return convert_array_to_object($contents);
    }

    public function findAll(): array
    {
        $sql = "SELECT * FROM {$this->dfdb->basePrefix}site";

        $data = $this->dfdb->getResults(query: $sql, output: Database::ARRAY_A);

        $contents = [];

        if (!is_null__($data)) {
            foreach ($data as $content) {
                $contents[] = $this->populate($content);
            }
        }

        return $contents;
    }
}
