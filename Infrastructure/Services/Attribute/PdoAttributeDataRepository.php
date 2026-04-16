<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Attribute;

use Qubus\Expressive\Database;

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
        $tableName = $this->dfdb->prefix . $type;
        if (empty($tableName)) {
            return '';
        }

        return $tableName;
    }

    /**
     * @param string $type
     * @param string $id
     * @return AttributeBag
     * @throws \Exception
     */
    public function getAttribute(string $type, string $id): AttributeBag
    {
        $table = $this->table($type);
        $stmt = $this->dfdb->getConnection()->pdo->prepare(
                "SELECT {$type}_attribute
             FROM {$table}
             WHERE {$type}_id = :id
             LIMIT 1"
        );

        $stmt->execute(['id' => $id]);

        $json = $stmt->fetchColumn();

        if ($json === false) {
            throw new \RuntimeException(sprintf("%s %s not found.", $type, $id));
        }

        return AttributeBag::fromJson(is_string($json) ? $json : null);
    }

    /**
     * @param string $type
     * @param string $id
     * @param AttributeBag $attribute
     * @return void
     * @throws \Exception
     */
    public function saveAttribute(string $type, string $id, AttributeBag $attribute): void
    {
        $table = $this->table($type);
        $stmt = $this->dfdb->getConnection()->pdo->prepare(
                "UPDATE {$table}
             SET {$type}_attribute = :attribute
             WHERE {$type}_id = :id"
        );

        $stmt->execute([
            'id' => $id,
            'attribute' => $attribute->toJson(),
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
        $this->dfdb->getConnection()->pdo->beginTransaction();

        try {
            $stmt = $this->dfdb->getConnection()->pdo->prepare(
                "SELECT {$type}_attribute
                 FROM {$table}
                 WHERE {$type}_id = :id
                 LIMIT 1
                 FOR UPDATE"
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
                "UPDATE {$table}
                 SET {$type}_attribute = :attribute
                 WHERE {$type}_id = :id"
            );

            $update->execute([
                'id' => $id,
                'attribute' => $updated->toJson(),
            ]);

            $this->dfdb->getConnection()->pdo->commit();

            return $updated;
        } catch (\Throwable $e) {
            $this->dfdb->getConnection()->pdo->rollBack();
            throw $e;
        }
    }
}
