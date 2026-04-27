<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Content\Query\Trait\PopulateContentQueryAware;
use App\Domain\Content\Repository\ContentQueryRepository;
use Qubus\Expressive\Database;
use App\Shared\Services\Sanitizer;
use PDOException;
use Qubus\Exception\Exception;
use ReflectionException;

use function Codefy\Framework\Helpers\logger;
use function is_array;
use function Qubus\Support\Helpers\convert_array_to_object;
use function Qubus\Support\Helpers\is_false__;
use function Qubus\Support\Helpers\is_null__;
use function sprintf;

class QueryBusContentRepository implements ContentQueryRepository
{
    use PopulateContentQueryAware;

    public function __construct(protected Database $dfdb)
    {
    }

    /**
     * @throws Exception
     */
    public function findById(string $id): array|object
    {
        $sql = "SELECT * FROM {$this->dfdb->prefix}content WHERE content_id = ? ORDER BY content_published DESC";

        $data = $this->dfdb->getRow($this->dfdb->prepare($sql, [$id]), Database::ARRAY_A);

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
    public function findBySlug(string $slug): array|object
    {
        $sql = "SELECT * FROM {$this->dfdb->prefix}content WHERE content_slug = ? ORDER BY content_published DESC";

        $data = $this->dfdb->getRow($this->dfdb->prepare($sql, [$slug]), Database::ARRAY_A);

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
    public function findByStatus(string $status): array
    {
        $sql = "SELECT * FROM {$this->dfdb->prefix}content WHERE content_status = ? ORDER BY content_published DESC";

        $data = $this->dfdb->getResults(
            query: $this->dfdb->prepare($sql, [$status]),
            output: Database::ARRAY_A
        );

        $contents = [];

        if (!is_null__($data)) {
            foreach ($data as $content) {
                $contents[] = $this->populate($content);
            }
        }

        return $contents;
    }

    /**
     * @throws Exception
     */
    public function findByTypeAndId(string $type, string $id): array|object
    {
        $sql = "SELECT * FROM {$this->dfdb->prefix}content WHERE content_type = ? AND content_id = ? ORDER BY content_published DESC";

        $data = $this->dfdb->getRow(
            $this->dfdb->prepare(
                $sql,
                [
                    $type,
                    $id
                ]
            ),
            Database::ARRAY_A
        );

        if (is_null__(var: $data)) {
            return [];
        }

        $content = $this->populate(data: $data);

        if (is_array(value: $content)) {
            $content = convert_array_to_object(array: $content);
        }

        return $content;
    }

    /**
     * @throws Exception
     */
    public function findByType(string $type): array|object
    {
        $sql = "SELECT * FROM {$this->dfdb->prefix}content WHERE content_type = ? ORDER BY content_published DESC";

        $data = $this->dfdb->getRow($this->dfdb->prepare($sql, [$type]), Database::ARRAY_A);

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
     * @throws ReflectionException
     */
    public function findByFilters(
        ?string $type = null,
        int $limit = 0,
        ?int $offset = null,
        string $status = 'all'
    ): array {
        $contents = [];
        $where = '';

        $sanitizeContentType = Sanitizer::item(item: $type);
        $sanitizeLimit = Sanitizer::item(item: $limit, type: 'int', context: '');
        $sanitizeOffset = Sanitizer::item(item: $offset, type: 'int', context: '');
        $sanitizeStatus = Sanitizer::item(item: $status);

        if (!is_null__($sanitizeContentType) && '' !== $sanitizeContentType) {
            $prepare = $this->dfdb->prepare(
                "SELECT * FROM {$this->dfdb->prefix}content WHERE content_type = ?",
                [
                    $sanitizeContentType
                ]
            );

            if ($sanitizeStatus !== 'all') {
                $where .= $this->dfdb->prepare(
                    " AND content_status = ?",
                    [
                        $sanitizeStatus
                    ]
                );
            }

            $where .= ' ORDER BY content_published DESC';

            if ($sanitizeLimit > 0 && !is_null__($sanitizeOffset)) {
                $where .= sprintf(" LIMIT %d OFFSET %d", $sanitizeLimit, $sanitizeOffset);
            } elseif ($sanitizeLimit > 0 && is_null__($sanitizeOffset)) {
                $where .= sprintf(" LIMIT %d", $sanitizeLimit);
            } elseif ($sanitizeLimit <= 0 && !is_null__($sanitizeOffset)) {
                $where .= sprintf(" OFFSET %d", $sanitizeOffset);
            }

            try {
                $results = $this->dfdb->getResults(query: $prepare . $where, output: Database::ARRAY_A);

                if (!is_false__($results)) {
                    foreach ($results as $content) {
                        $contents[] = $this->populate($content);
                    }
                }

                return $contents;
            } catch (PDOException $e) {
                logger(
                    'error',
                    sprintf(
                        'SQLSTATE[%s]: %s',
                        $e->getCode(),
                        $e->getMessage()
                    ),
                    [
                        'QueryBusContentRepository' => 'findByFilters'
                    ]
                );
            }
        } else {
            if ($sanitizeStatus !== 'all') {
                $where = $this->dfdb->prepare(
                    " WHERE content_status = ?",
                    [
                        $sanitizeStatus
                    ]
                );
            }

            $where .= ' ORDER BY content_published DESC';

            if ($sanitizeLimit > 0 && !is_null__($sanitizeOffset)) {
                $where .= sprintf(" LIMIT %d OFFSET %d", $sanitizeLimit, $sanitizeOffset);
            } elseif ($sanitizeLimit > 0 && is_null__($sanitizeOffset)) {
                $where .= sprintf(" LIMIT %d", $sanitizeLimit);
            } elseif ($sanitizeLimit <= 0 && !is_null__($sanitizeOffset)) {
                $where .= sprintf(" OFFSET %d", $sanitizeOffset);
            }

            try {
                $results = $this->dfdb->getResults(
                    "SELECT * FROM {$this->dfdb->prefix}content{$where}",
                    Database::ARRAY_A
                );

                if (!is_false__($results)) {
                    foreach ($results as $content) {
                        $contents[] = $this->populate($content);
                    }
                }

                return $contents;
            } catch (PDOException $e) {
                logger(
                    'error',
                    sprintf(
                        'SQLSTATE[%s]: %s',
                        $e->getCode(),
                        $e->getMessage()
                    ),
                    [
                        'QueryBusContentRepository' => 'findByFilters',
                    ]
                );
            }
        }

        return $contents;
    }
}
