<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Content\Workflow;

use JsonException;
use Qubus\Expressive\Database;

use RuntimeException;

use function App\Shared\Helpers\current_user_can;

final readonly class ContentRevisionDiffService
{
    public function __construct(private Database $dfdb)
    {
    }

    /**
     * @throws JsonException
     */
    public function diff(string $contentId, string $eventId): array
    {
        if (false === current_user_can(perm: 'view:content_revisions')) {
            throw new RuntimeException('Access denied.');
        }

        $current = $this->revisionPayload($contentId, $eventId);
        $previous = $this->previousRevisionPayload($contentId, $eventId);

        return $this->compare($previous, $current);
    }

    /**
     * @throws JsonException
     */
    private function revisionPayload(string $contentId, string $eventId): array
    {
        $event = $this->dfdb
            ->table($this->dfdb->prefix . 'event_store')
            ->where('aggregate_id', $contentId)
            ->where('event_id', $eventId)
            ->findOne();

        if ($event === false) {
            throw new \RuntimeException('Revision not found.');
        }

        return $this->normalizePayload(
            json_decode((string) $event->payload, true, 512, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * @throws JsonException
     */
    private function previousRevisionPayload(string $contentId, string $eventId): array
    {
        $event = $this->dfdb
            ->table($this->dfdb->prefix . 'event_store')
            ->where('aggregate_id', $contentId)->and()
            ->where('event_id', $eventId)
            ->findOne();

        if ($event === false) {
            return [];
        }

        $previous = $this->dfdb
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
            ->where('aggregate_playhead < ?', $event->aggregate_playhead)
            ->orderBy('aggregate_playhead', 'DESC')
            ->limit(1)
            ->findOne();

        if ($previous === false) {
            return [];
        }

        return $this->normalizePayload(
                json_decode((string) $previous->payload, true, 512, JSON_THROW_ON_ERROR)
        );
    }

    private function normalizePayload(array $payload): array
    {
        $data = $payload['content'] ?? $payload;

        return [
            'title' => $data['title'] ?? $data['content_title'] ?? '',
            'slug' => $data['slug'] ?? $data['content_slug'] ?? '',
            'body' => $data['body'] ?? $data['content_body'] ?? '',
            'status' => $data['status'] ?? $data['content_status'] ?? '',
            'featured_image' => $data['featuredImage'] ?? $data['content_featured_image'] ?? '',
            'attribute' => $data['attribute'] ?? $data['content_attribute'] ?? [],
        ];
    }

    private function compare(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $field => $newValue) {
            $oldValue = $before[$field] ?? null;

            if ($oldValue !== $newValue) {
                $changes[] = [
                    'field' => $field,
                    'before' => is_scalar($oldValue) ? (string) $oldValue : json_encode($oldValue),
                    'after' => is_scalar($newValue) ? (string) $newValue : json_encode($newValue),
                ];
            }
        }

        return $changes;
    }
}
