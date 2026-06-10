<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Attribute;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Qubus\Exception\Exception;
use Qubus\Expressive\Database;
use ReflectionException;

use function is_string;
use function sprintf;

final readonly class PdoAttributeDataRepository implements AttributeRepository
{
    public function __construct(private Database $dfdb)
    {
    }

    /**
     * Retrieve the name of the attribute table for the specified object type.
     *
     * @param string $type Type of object to get attribute table for (e.g. content, or product).
     * @return string Table name or empty string if not exist.
     */
    private function table(string $type): string
    {
        return match ($type) {
            'page', 'pages' => $this->dfdb->prefix . 'pages',
            default => $this->dfdb->prefix . $type,
        };
    }

    private function attributeColumn(string $type): string
    {
        return match ($type) {
            'page', 'pages' => 'page_attribute',
            default => $type . '_attribute',
        };
    }

    private function idColumn(string $type): string
    {
        return match ($type) {
            'page', 'pages' => 'id',
            default => $type . '_id',
        };
    }

    /**
     * @param string $type
     * @param string $id
     * @return AttributeBag
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     * @throws ReflectionException
     */
    public function getAttribute(string $type, string $id): AttributeBag
    {
        $table = $this->table($type);
        $attributeColumn = $this->attributeColumn($type);
        $stmt = $this->dfdb->getConnection()->pdo->prepare(
            sprintf(
                "SELECT %s
                FROM %s
                WHERE %s = :id
                LIMIT 1",
                $attributeColumn,
                $table,
                $this->idColumn($type)
            )
        );

        $stmt->execute(['id' => $id]);

        $json = $stmt->fetchColumn();

        if ($json === false) {
            throw new \RuntimeException(sprintf("%s %s not found.", $type, $id));
        }

        $bag = AttributeBag::fromJson(is_string($json) ? $json : null);

        return $bag->withExpandedUrls();
    }

    /**
     * @param string $type
     * @param string $id
     * @param AttributeBag $attribute
     * @return void
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function saveAttribute(string $type, string $id, AttributeBag $attribute): void
    {
        $table = $this->table($type);
        $attributeColumn = $this->attributeColumn($type);
        $stmt = $this->dfdb->getConnection()->pdo->prepare(
            sprintf(
                "UPDATE %s
                SET %s = :attribute
                WHERE %s = :id",
                $table,
                $attributeColumn,
                $this->idColumn($type)
            )
        );

        $stmt->execute([
            'id' => $id,
            'attribute' => $attribute->withCompressedUrls()->toJson(),
        ]);
    }

    /**
     * @param string $type
     * @param string $id
     * @param callable $callback
     * @return AttributeBag
     * @throws \Throwable
     */
    public function patchAttribute(string $type, string $id, callable $callback): AttributeBag
    {
        $table = $this->table($type);
        $attributeColumn = $this->attributeColumn($type);
        $this->dfdb->getConnection()->pdo->beginTransaction();

        try {
            $stmt = $this->dfdb->getConnection()->pdo->prepare(
                sprintf(
                    "SELECT %s
                    FROM %s
                    WHERE %s = :id
                    LIMIT 1",
                    $attributeColumn,
                    $table,
                    $this->idColumn($type)
                )
            );

            $stmt->execute(['id' => $id]);

            $json = $stmt->fetchColumn();

            if ($json === false) {
                throw new \RuntimeException(sprintf("%s %s not found.", $type, $id));
            }

            $current = AttributeBag::fromJson(is_string($json) ? $json : null);
            $updated = $callback($current);

            if (!$updated instanceof AttributeBag) {
                throw new \RuntimeException('Attribute patch callback must return an AttributeBag.');
            }

            $update = $this->dfdb->getConnection()->pdo->prepare(
                sprintf(
                    "UPDATE %s
                    SET %s = :attribute
                    WHERE %s = :id",
                    $table,
                    $attributeColumn,
                    $this->idColumn($type)
                )
            );

            $update->execute([
                'id' => $id,
                'attribute' => $updated->withCompressedUrls()->toJson(),
            ]);

            $this->dfdb->getConnection()->pdo->commit();

            return $updated;
        } catch (\Throwable $e) {
            $this->dfdb->getConnection()->pdo->rollBack();
            throw $e;
        }
    }
}
