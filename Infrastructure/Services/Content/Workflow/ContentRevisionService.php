<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Content\Workflow;

use JsonException;
use Qubus\Expressive\Database;

use function App\Shared\Helpers\current_user_can;

final readonly class ContentRevisionService
{
    public function __construct(private Database $dfdb)
    {
    }

    public function revisions(string $contentId, int $limit = 25): array
    {
        $rows = $this->dfdb
            ->table($this->dfdb->prefix . 'event_store')
            ->where('aggregate_id', $contentId)
            ->orderBy('aggregate_playhead', 'DESC')
            ->limit($limit)
            ->find(callback: static fn(array $rows): array => $rows);

        return array_map(fn(array $row): array => $this->normalizeRevision($row), $rows);
    }

    /**
     * @throws JsonException
     */
    public function restore(string $contentId, string $eventId): void
    {
        if (false === current_user_can(perm: 'restore:content_revisions')) {
            throw new \RuntimeException('Access denied.');
        }

        $event = $this->dfdb
            ->table($this->dfdb->prefix . 'event_store')
            ->where('event_id', $eventId)
            ->where('aggregate_id', $contentId)
            ->findOne();

        if ($event === false) {
            throw new \RuntimeException('Revision not found.');
        }

        $payload = json_decode((string) $event->payload, true, 512, JSON_THROW_ON_ERROR);

        $data = $payload['content'] ?? $payload;

        $this->dfdb
            ->table($this->dfdb->prefix . 'content')
            ->where('content_id', $contentId)
            ->update([
                'content_title' => $data['title'] ?? $data['content_title'] ?? '',
                'content_slug' => $data['slug'] ?? $data['content_slug'] ?? '',
                'content_body' => $data['body'] ?? $data['content_body'] ?? '',
                'content_attribute' => json_encode($data['attribute'] ?? $data['content_attribute'] ?? [], JSON_THROW_ON_ERROR),
                'content_status' => 'draft',
                'content_modified_gmt' => gmdate('Y-m-d H:i:s'),
            ]);
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
}
