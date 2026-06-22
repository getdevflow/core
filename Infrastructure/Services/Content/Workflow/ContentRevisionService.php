<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Content\Workflow;

use App\Application\Devflow;
use App\Domain\Content\Command\RestoreRevisionCommand;
use App\Domain\Content\ValueObject\ContentId;
use App\Shared\ValueObject\ArrayLiteral;
use Codefy\CommandBus\Exceptions\CommandPropertyNotFoundException;
use Codefy\CommandBus\Exceptions\UnresolvableCommandHandlerException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Expressive\Database;
use Qubus\Support\DateTime\QubusDateTimeImmutable;
use Qubus\ValueObjects\StringLiteral\StringLiteral;
use ReflectionException;
use RuntimeException;

use function App\Shared\Helpers\current_user_can;
use function array_filter;
use function array_key_exists;
use function array_map;
use function Codefy\Framework\Helpers\command;
use function Codefy\Framework\Helpers\logger;
use function implode;
use function is_array;
use function is_string;
use function json_decode;

final readonly class ContentRevisionService
{
    public function __construct(private Database $dfdb)
    {
    }

    public function revisions(string $contentId, int $limit = 25): array
    {
        $rows = $this->dfdb
            ->table($this->dfdb->prefix . 'event_store')
            ->where('aggregate_id', $contentId)->and()
            ->whereNotIn(
                'event_type',
                [
                    'content-parent-was-removed',
                    'content-published-was-changed',
                    'content-published-gmt-was-changed',
                    'content-modified-was-changed',
                    'content-modified-gmt-was-changed'
                ]
            )
            ->orderBy('aggregate_playhead', 'DESC')
            ->limit($limit)
            ->find(callback: static fn(array $rows): array => $rows);

        return array_map(fn(array $row): array => $this->normalizeRevision($row), $rows);
    }

    /**
     * @param string $contentId
     * @param string $eventId
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function restore(string $contentId, string $eventId): void
    {
        if (false === current_user_can(perm: 'restore:content_revisions')) {
            throw new \RuntimeException('Access denied.');
        }

        $data = $this->snapshotForRevision($contentId, $eventId);

        try {
            command(
                new RestoreRevisionCommand([
                    'id' => ContentId::fromString($contentId),
                    'title' => StringLiteral::fromNative($data['content_title']),
                    'slug' => StringLiteral::fromNative($data['content_slug']),
                    'body' => StringLiteral::fromNative($data['content_body']),
                    'attribute' => ArrayLiteral::fromNative($data['content_attribute']),
                    'status' => StringLiteral::fromNative('draft'),
                    'modified' => QubusDateTimeImmutable::now(),
                    'modifiedGmt' => QubusDateTimeImmutable::now('UTC'),
                ])
            );
            Devflow::$PHP->flash->success(Devflow::$PHP->flash->notice(200));
        } catch (UnresolvableCommandHandlerException | ReflectionException | CommandPropertyNotFoundException $e) {
            Devflow::$PHP->flash->error(Devflow::$PHP->flash->notice(204));
            logger('error', $e->getMessage());
        }
    }

    private function normalizeRevision(array $row): array
    {
        $payload = json_decode((string) $row['payload'], true) ?: [];
        $metadata = json_decode((string) $row['metadata'], true) ?: [];

        return [
            'event_id' => $row['event_id'],
            'event_type' => $row['event_type'],
            'playhead' => $row['aggregate_playhead'],
            'recorded_at' => $row['recorded_at'],
            'user_id' => $metadata['user_id'] ?? $metadata['userId'] ?? null,
            'title' => $payload['title'] ?? $payload['content_title'] ?? $payload['content']['title'] ?? null,
            'status' => $payload['status'] ?? $payload['content_status'] ?? $payload['content']['status'] ?? null,
            'payload' => $payload,
            'metadata' => $metadata,
        ];
    }

    private function snapshotForRevision(string $contentId, string $eventId): array
    {
        $target = $this->dfdb
            ->table($this->dfdb->prefix . 'event_store')
            ->where('event_id', $eventId)->and()
            ->where('aggregate_id', $contentId)
            ->findOne();

        if ($target === false) {
            throw new RuntimeException('Revision not found.');
        }

        $events = $this->dfdb
            ->table($this->dfdb->prefix . 'event_store')
            ->where('aggregate_id', $contentId)->and()
            ->whereNotIn(
                'event_type',
                [
                    'content-parent-was-removed',
                    'content-published-was-changed',
                    'content-published-gmt-was-changed',
                    'content-modified-was-changed',
                    'content-modified-gmt-was-changed'
                ]
            )->and()
            ->where('aggregate_playhead <= ?', $target->aggregate_playhead)
            ->orderBy('aggregate_playhead', 'DESC')
            ->find(callback: static fn(array $rows): array => $rows);

        $snapshot = [
            'content_title' => null,
            'content_slug' => null,
            'content_body' => null,
            'content_attribute' => null,
        ];

        foreach ($events as $event) {
            $payload = json_decode((string) $event['payload'], true);

            if (! is_array($payload)) {
                continue;
            }

            $normalized = $this->normalizeRevisionPayload($payload);

            foreach ($snapshot as $field => $value) {
                if ($value === null && array_key_exists($field, $normalized)) {
                    $snapshot[$field] = $normalized[$field];
                }
            }

            if (
                    $snapshot['content_title'] !== null &&
                    $snapshot['content_slug'] !== null &&
                    $snapshot['content_body'] !== null &&
                    $snapshot['content_attribute'] !== null
            ) {
                break;
            }
        }

        return $this->assertCompleteSnapshot($snapshot);
    }

    private function normalizeRevisionPayload(array $payload): array
    {
        $data = $payload['content'] ?? $payload;

        $attribute = $data['attribute']
            ?? $data['content_attribute']
            ?? null;

        if (is_string($attribute)) {
            $decoded = json_decode($attribute, true);
            $attribute = is_array($decoded) ? $decoded : [];
        }

        return array_filter([
            'content_title' => $data['title'] ?? $data['content_title'] ?? null,
            'content_slug' => $data['slug'] ?? $data['content_slug'] ?? null,
            'content_body' => $data['body'] ?? $data['content_body'] ?? null,
            'content_attribute' => $attribute,
        ], static fn($value): bool => $value !== null);
    }

    private function assertCompleteSnapshot(array $snapshot): array
    {
        $missing = [];

        foreach (['content_title', 'content_slug', 'content_body', 'content_attribute'] as $field) {
            if ($snapshot[$field] === null) {
                $missing[] = $field;
            }
        }

        if ($missing !== []) {
            throw new RuntimeException(
                'Cannot restore this revision because the event history does not contain a complete snapshot. Missing: ' .
                implode(', ', $missing)
            );
        }

        if (is_string($snapshot['content_attribute'])) {
            $decoded = json_decode($snapshot['content_attribute'], true);
            $snapshot['content_attribute'] = is_array($decoded) ? $decoded : [];
        }

        return $snapshot;
    }
}
