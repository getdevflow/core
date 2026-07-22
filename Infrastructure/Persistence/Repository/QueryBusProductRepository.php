<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Product\Query\Trait\PopulateProductQueryAware;
use App\Domain\Product\Repository\ProductQueryRepository;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
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

class QueryBusProductRepository implements ProductQueryRepository
{
    use PopulateProductQueryAware;

    public function __construct(protected Database $dfdb)
    {
    }

    /**
     * @param string $id
     * @return array|object
     * @throws Exception
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function findById(string $id): array|object
    {
        $sql = "SELECT * FROM {$this->dfdb->prefix}product WHERE product_id = ?";

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
     * @param string $sku
     * @return array|object
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function findBySku(string $sku): array|object
    {
        $sql = "SELECT * FROM {$this->dfdb->prefix}product WHERE product_sku = ?";

        $data = $this->dfdb->getRow($this->dfdb->prepare($sql, [$sku]), Database::ARRAY_A);

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
     * @param string $slug
     * @return array|object
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function findBySlug(string $slug): array|object
    {
        $sql = "SELECT * FROM {$this->dfdb->prefix}product WHERE product_slug = ?";

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
     * @param string|null $sku
     * @param int $limit
     * @param int|null $offset
     * @param string $status
     * @return array
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function findByFilters(
        ?string $sku = null,
        int $limit = 0,
        ?int $offset = null,
        string $status = 'all'
    ): array {
        $products = [];
        $where = '';

        $sanitizeProductSku = Sanitizer::item(item: $sku);
        $sanitizeLimit = Sanitizer::item(item: $limit, type: 'int', context: '');
        $sanitizeOffset = Sanitizer::item(item: $offset, type: 'int', context: '');
        $sanitizeStatus = Sanitizer::item(item: $status);

        if (!is_null__($sanitizeProductSku)) {
            $prepare = $this->dfdb->prepare(
                "SELECT * FROM {$this->dfdb->prefix}product WHERE product_sku = ?",
                [
                    $sanitizeProductSku
                ]
            );

            if ($sanitizeStatus !== 'all') {
                $where .= $this->dfdb->prepare(
                    " AND WHERE product_status = ?",
                    [
                        $sanitizeStatus
                    ]
                );
            }

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
                    foreach ($results as $product) {
                        $products[] = $this->populate($product);
                    }
                }

                return $products;
            } catch (PDOException $e) {
                logger(
                    'error',
                    sprintf(
                        'SQLSTATE[%s]: %s',
                        $e->getCode(),
                        $e->getMessage()
                    ),
                    [
                        'QueryBusProductRepository' => 'findByFilters'
                    ]
                );
            }
        } else {
            if ($sanitizeStatus !== 'all') {
                $where = $this->dfdb->prepare(
                    " WHERE product_status = ?",
                    [
                        $sanitizeStatus
                    ]
                );
            }

            if ($sanitizeLimit > 0 && !is_null__($sanitizeOffset)) {
                $where .= sprintf(" LIMIT %d OFFSET %d", $sanitizeLimit, $sanitizeOffset);
            } elseif ($sanitizeLimit > 0 && is_null__($sanitizeOffset)) {
                $where .= sprintf(" LIMIT %d", $sanitizeLimit);
            } elseif ($sanitizeLimit <= 0 && !is_null__($sanitizeOffset)) {
                $where .= sprintf(" OFFSET %d", $sanitizeOffset);
            }

            try {
                $results = $this->dfdb->getResults(
                    "SELECT * FROM {$this->dfdb->prefix}product{$where}",
                    Database::ARRAY_A
                );

                if (!is_false__($results)) {
                    foreach ($results as $product) {
                        $products[] = $this->populate($product);
                    }
                }

                return $products;
            } catch (PDOException $e) {
                logger(
                    'error',
                    sprintf(
                        'SQLSTATE[%s]: %s',
                        $e->getCode(),
                        $e->getMessage()
                    ),
                    [
                        'QueryBusProductRepository' => 'findByFilters',
                    ]
                );
            }
        }

        return $products;
    }
}
