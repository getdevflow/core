<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Product\Query\Trait\PopulateProductQueryAware;
use App\Domain\Product\Repository\ProductQueryRepository;
use App\Infrastructure\Persistence\Database;
use App\Shared\Services\Sanitizer;
use Codefy\Framework\Factory\FileLoggerFactory;
use PDOException;
use Qubus\Exception\Exception;
use ReflectionException;

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
     * @throws ReflectionException
     * @throws Exception
     */
    public function findById(string $productId): array|object
    {
        $sql = "SELECT * FROM {$this->dfdb->prefix}product WHERE product_id = ?";

        $data = $this->dfdb->getRow($this->dfdb->prepare($sql, [$productId]), Database::ARRAY_A);

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
     * @throws ReflectionException
     * @throws Exception
     */
    public function findBySku(string $productSku): array|object
    {
        $sql = "SELECT * FROM {$this->dfdb->prefix}product WHERE product_sku = ?";

        $data = $this->dfdb->getRow($this->dfdb->prepare($sql, [$productSku]), Database::ARRAY_A);

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
     * @throws ReflectionException
     * @throws Exception
     */
    public function findBySlug(string $productSlug): array|object
    {
        $sql = "SELECT * FROM {$this->dfdb->prefix}product WHERE product_slug = ?";

        $data = $this->dfdb->getRow($this->dfdb->prepare($sql, [$productSlug]), Database::ARRAY_A);

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
        ?string $productSku = null,
        int $limit = 0,
        ?int $offset = null,
        string $status = 'all'
    ): array {
        $products = [];
        $where = '';

        $sanitizeProductSku = Sanitizer::item(item: $productSku);
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
                FileLoggerFactory::getLogger()->error(
                    sprintf(
                        'SQLSTATE[%s]: %s',
                        $e->getCode(),
                        $e->getMessage()
                    ),
                    [
                        'Db Function' => 'get_all_products'
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
                FileLoggerFactory::getLogger()->error(
                    sprintf(
                        'SQLSTATE[%s]: %s',
                        $e->getCode(),
                        $e->getMessage()
                    ),
                    [
                        'Query Bus' => 'QueryBusProductRepository',
                    ]
                );
            }
        }

        return $products;
    }
}
