<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Content\Workflow;

use JsonException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Expressive\Database;
use ReflectionException;
use RuntimeException;

use function App\Shared\Helpers\current_user_can;

final readonly class ContentRevisionDiffService
{
    private const array REVISION_EVENT_TYPES = [
        'content-was-created',
        'content-title-was-changed',
        'content-slug-was-changed',
        'content-body-was-changed',
    ];

    public function __construct(private Database $dfdb)
    {
    }

    /**
     * @param string $contentId
     * @param string $eventId
     * @return array
     * @throws JsonException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     * @throws TypeException
     * @throws Exception
     * @throws ReflectionException
     */
    public function diff(string $contentId, string $eventId): array
    {
        if (false === current_user_can(perm: 'view:content_revisions')) {
            throw new RuntimeException('Access denied.');
        }

        $target = $this->revisionEvent($contentId, $eventId);

        $current = $this->snapshotAtPlayhead(
            contentId: $contentId,
            playhead: (int) $target['aggregate_playhead']
        );

        $previous = $this->snapshotBeforePlayhead(
            contentId: $contentId,
            playhead: (int) $target['aggregate_playhead']
        );

        return $this->compare($previous, $current);
    }

    private function normalizePayload(array $payload): array
    {
        $data = $payload['content'] ?? $payload;

        return array_filter([
            'title' => $data['title'] ?? $data['content_title'] ?? null,
            'slug' => $data['slug'] ?? $data['content_slug'] ?? null,
            'body' => $data['body'] ?? $data['content_body'] ?? null,
        ], static fn(mixed $value): bool => $value !== null);
    }

    private function compare(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $field => $newValue) {
            $oldValue = $before[$field] ?? '';

            if ($oldValue !== $newValue) {
                $changes[] = [
                    'field' => match ($field) {
                        'title' => 'Title',
                        'slug' => 'Slug',
                        'body' => 'Body',
                        default => $field,
                    },
                    'before' => is_scalar($oldValue) ? (string) $oldValue : json_encode($oldValue),
                    'after' => is_scalar($newValue) ? (string) $newValue : json_encode($newValue),
                ];
            }
        }

        return $changes;
    }

    private function revisionEvent(string $contentId, string $eventId): array
    {
        $event = $this->dfdb
            ->table($this->dfdb->prefix . 'event_store')
            ->where('aggregate_id', $contentId)->and()
            ->where('event_id', $eventId)->and()
            ->whereIn('event_type', self::REVISION_EVENT_TYPES)
            ->findOne();

        if ($event === false) {
            throw new RuntimeException('Revision not found.');
        }

        return $event->toArray();
    }

    private function snapshotAtPlayhead(string $contentId, int $playhead): array
    {
        return $this->snapshotForPlayhead($contentId, $playhead, inclusive: true);
    }

    private function snapshotBeforePlayhead(string $contentId, int $playhead): array
    {
        return $this->snapshotForPlayhead($contentId, $playhead, inclusive: false);
    }

    private function snapshotForPlayhead(string $contentId, int $playhead, bool $inclusive): array
    {
        $operator = $inclusive ? '<= ?' : '< ?';

        $events = $this->dfdb
            ->table($this->dfdb->prefix . 'event_store')
            ->where('aggregate_id', $contentId)->and()
            ->whereIn('event_type', self::REVISION_EVENT_TYPES)->and()
            ->where('aggregate_playhead ' . $operator, $playhead)
            ->orderBy('aggregate_playhead', 'DESC')
            ->find(callback: static fn(array $rows): array => $rows);

        $snapshot = [
            'title' => null,
            'slug' => null,
            'body' => null,
        ];

        foreach ($events as $event) {
            $payload = json_decode((string) $event['payload'], true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($payload)) {
                continue;
            }

            $normalized = $this->normalizePayload($payload);

            foreach ($snapshot as $field => $value) {
                if ($value === null && array_key_exists($field, $normalized)) {
                    $snapshot[$field] = $normalized[$field];
                }
            }

            if ($snapshot['title'] !== null && $snapshot['slug'] !== null && $snapshot['body'] !== null) {
                break;
            }
        }

        return array_filter(
            $snapshot,
            static fn(mixed $value): bool => $value !== null
        );
    }
}
