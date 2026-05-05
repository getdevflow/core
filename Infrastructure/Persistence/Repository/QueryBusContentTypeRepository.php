<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\ContentType\Query\Trait\PopulateContentTypeQueryAware;
use App\Domain\ContentType\Repository\ContentTypeQueryRepository;
use Qubus\Expressive\Database;
use Qubus\Exception\Exception;
use ReflectionException;

use function is_array;
use function Qubus\Support\Helpers\convert_array_to_object;
use function Qubus\Support\Helpers\is_null__;

class QueryBusContentTypeRepository implements ContentTypeQueryRepository
{
    use PopulateContentTypeQueryAware;

    public function __construct(protected Database $dfdb)
    {
    }

    /**
     * @throws Exception
     */
    public function findById(string $contentTypeId): array|null|object
    {
        $sql = "SELECT * FROM {$this->dfdb->prefix}content_type WHERE content_type_id = ?";

        $data = $this->dfdb->getRow(
            query: $this->dfdb->prepare($sql, [$contentTypeId]),
            output: Database::ARRAY_A
        );

        if (is_null__($data)) {
            return [];
        }

        $content = $this->populate($data);

        if (is_array($content)) {
            $content = convert_array_to_object($content);
        }

        return $content;
    }

    /**
     * @throws Exception
     */
    public function findBySlug(string $contentTypeSlug): array|null|object
    {
        $sql = "SELECT * FROM {$this->dfdb->prefix}content_type WHERE content_type_slug = ?";

        $data = $this->dfdb->getRow(
            query: $this->dfdb->prepare($sql, [$contentTypeSlug]),
            output: Database::ARRAY_A
        );

        if (is_null__($data)) {
            return [];
        }

        $content = $this->populate($data);

        if (is_array($content)) {
            $content = convert_array_to_object($content);
        }

        return $content;
    }

    /**
     * @throws Exception
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM {$this->dfdb->prefix}content_type";

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
