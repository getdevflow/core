<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Content\Workflow;

use JsonException;
use Qubus\Expressive\Database;
use Qubus\Support\DateTime\QubusDateTimeImmutable;
use Qubus\ValueObjects\Identity\Ulid;

use function App\Shared\Helpers\current_user_can;

final readonly class ContentWorkflowService
{
    public function __construct(private Database $dfdb)
    {
    }

    /**
     * @throws JsonException
     */
    public function requestReview(string $contentId, string $userId, array $reviewers, string $message = ''): array
    {
        $this->assertCan('review:content');

        return $this->dfdb->transactional(function () use ($contentId, $userId, $reviewers, $message): array {
            $content = $this->content($contentId);
            $attribute = $this->attribute($content['content_attribute'] ?? null);

            $attribute['workflow'] = array_merge($attribute['workflow'] ?? [], [
                'stage' => 'in_review',
                'reviewers' => array_values($reviewers),
                'approval_required' => true,
            ]);

            $this->updateContent($contentId, 'pending', $attribute);

            $activityId = $this->log(
                contentId: $contentId,
                userId: $userId,
                type: 'review_requested',
                fromStatus: (string) $content['content_status'],
                toStatus: 'pending',
                message: $message,
                metadata: ['reviewers' => $reviewers]
            );

            $this->notifyMany(
                contentId: $contentId,
                userIds: $reviewers,
                activityId: $activityId,
                type: 'review_requested',
                title: 'Content ready for review',
                body: $message
            );

            return [
                'activity_id' => $activityId,
                'status' => 'pending',
                'stage' => 'in_review',
            ];
        });
    }

    /**
     * @throws JsonException
     */
    public function approve(string $contentId, string $userId, string $message = ''): array
    {
        $this->assertCan('approve:content');

        return $this->dfdb->transactional(function () use ($contentId, $userId, $message): array {
            $content = $this->content($contentId);
            $attribute = $this->attribute($content['content_attribute'] ?? null);

            $attribute['workflow'] = array_merge($attribute['workflow'] ?? [], [
                'stage' => 'approved',
                'approved_by' => $userId,
                'approved_at' => $this->now(),
            ]);

            $this->updateContent($contentId, 'pending', $attribute);

            $activityId = $this->log(
                contentId: $contentId,
                userId: $userId,
                type: 'approved',
                fromStatus: (string) $content['content_status'],
                toStatus: 'pending',
                message: $message
            );

            return [
                'activity_id' => $activityId,
                'status' => 'pending',
                'stage' => 'approved',
            ];
        });
    }

    /**
     * @throws JsonException
     */
    public function requestChanges(string $contentId, string $userId, string $message = ''): array
    {
        $this->assertCan('review:content');

        return $this->dfdb->transactional(function () use ($contentId, $userId, $message): array {
            $content = $this->content($contentId);
            $attribute = $this->attribute($content['content_attribute'] ?? null);

            $attribute['workflow'] = array_merge($attribute['workflow'] ?? [], [
                'stage' => 'changes_requested',
            ]);

            $this->updateContent($contentId, 'draft', $attribute);

            $activityId = $this->log(
                contentId: $contentId,
                userId: $userId,
                type: 'changes_requested',
                fromStatus: (string) $content['content_status'],
                toStatus: 'draft',
                message: $message
            );

            return [
                'activity_id' => $activityId,
                'status' => 'draft',
                'stage' => 'changes_requested',
            ];
        });
    }

    /**
     * @throws JsonException
     */
    public function publish(string $contentId, string $userId, string $message = ''): array
    {
        $this->assertCan('publish:content');

        return $this->dfdb->transactional(function () use ($contentId, $userId, $message): array {
            $content = $this->content($contentId);
            $attribute = $this->attribute($content['content_attribute'] ?? null);

            $attribute['workflow'] = array_merge($attribute['workflow'] ?? [], [
                'stage' => 'published',
                'published_by' => $userId,
                'published_at' => $this->now(),
            ]);

            $this->updateContent($contentId, 'published', $attribute);

            $activityId = $this->log(
                contentId: $contentId,
                userId: $userId,
                type: 'published',
                fromStatus: (string) $content['content_status'],
                toStatus: 'published',
                message: $message
            );

            return [
                'activity_id' => $activityId,
                'status' => 'published',
                'stage' => 'published',
            ];
        });
    }

    /**
     * @throws JsonException
     */
    public function comment(
        string $contentId,
        string $userId,
        string $body,
        ?string $parentId = null,
        ?array $selection = null
    ): array {
        $this->assertCan('update:content');

        $commentId = Ulid::generateAsString();

        $this->dfdb->table($this->dfdb->prefix . 'content_comment')->insert([
            'comment_id' => $commentId,
            'content_id' => $contentId,
            'parent_id' => $parentId,
            'user_id' => $userId,
            'comment_body' => $body,
            'comment_status' => 'open',
            'comment_type' => 'editorial',
            'selection_json' => $selection !== null ? json_encode($selection, JSON_THROW_ON_ERROR) : null,
            'created_at' => $this->now(),
            'updated_at' => null,
        ]);

        $activityId = $this->log(
            contentId: $contentId,
            userId: $userId,
            type: 'comment_added',
            message: $body,
            metadata: ['comment_id' => $commentId]
        );

        return [
            'comment_id' => $commentId,
            'activity_id' => $activityId,
        ];
    }

    public function publishScheduled(string $contentId): array
    {
        return $this->dfdb->transactional(function () use ($contentId): array {
            $content = $this->content($contentId);

            if ($content['content_status'] !== 'scheduled') {
                throw new \RuntimeException('Content is not scheduled.');
            }

            if (strtotime((string) $content['content_published_gmt']) > time()) {
                throw new \RuntimeException('Scheduled publish date has not passed.');
            }

            $attribute = $this->attribute($content['content_attribute'] ?? null);

            $attribute['workflow'] = array_merge($attribute['workflow'] ?? [], [
                'stage' => 'published',
                'published_by' => 'system',
                'published_at' => $this->now(),
            ]);

            $this->updateContent($contentId, 'published', $attribute);

            $activityId = $this->log(
                contentId: $contentId,
                userId: 'system',
                type: 'scheduled_published',
                fromStatus: 'scheduled',
                toStatus: 'published',
                message: 'Scheduled content was published automatically.'
            );

            return [
                'activity_id' => $activityId,
                'status' => 'published',
                'stage' => 'published',
            ];
        });
    }

    public function activity(string $contentId, int $limit = 25): array
    {
        return $this->dfdb
            ->table($this->dfdb->prefix . 'content_workflow_activity')
            ->where('content_id', $contentId)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->find(callback: static fn(array $rows): array => $rows);
    }

    public function comments(string $contentId): array
    {
        return $this->dfdb
            ->table($this->dfdb->prefix . 'content_comment')
            ->where('content_id', $contentId)
            ->orderBy('created_at', 'DESC')
            ->find(callback: static fn(array $rows): array => $rows);
    }

    public function revisions(string $contentId, int $limit = 25): array
    {
        return $this->dfdb
            ->table($this->dfdb->prefix . 'event_store')
            ->where('aggregate_id', $contentId)
            ->orderBy('aggregate_playhead', 'DESC')
            ->limit($limit)
            ->find(callback: static fn(array $rows): array => $rows);
    }

    private function content(string $contentId): array
    {
        $row = $this->dfdb
            ->table($this->dfdb->prefix . 'content')
            ->where('content_id', $contentId)
            ->findOne();

        if ($row === false) {
            throw new \RuntimeException('Content not found.');
        }

        return $row->toArray();
    }

    /**
     * @throws JsonException
     */
    private function attribute(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @throws JsonException
     */
    private function updateContent(string $contentId, string $status, array $attribute): void
    {
        $this->dfdb
            ->table($this->dfdb->prefix . 'content')
            ->where('content_id', $contentId)
            ->update([
                'content_status' => $status,
                'content_attribute' => json_encode($attribute, JSON_THROW_ON_ERROR),
                'content_modified_gmt' => gmdate('Y-m-d H:i:s'),
            ]);
    }

    /**
     * @throws JsonException
     */
    private function log(
        string $contentId,
        string $userId,
        string $type,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        string $message = '',
        array $metadata = []
    ): string {
        $activityId = Ulid::generateAsString();

        $this->dfdb->table($this->dfdb->prefix . 'content_workflow_activity')->insert([
            'activity_id' => $activityId,
            'content_id' => $contentId,
            'user_id' => $userId,
            'activity_type' => $type,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'message' => $message,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_at' => $this->now(),
        ]);

        return $activityId;
    }

    private function notifyMany(
        string $contentId,
        array $userIds,
        string $activityId,
        string $type,
        string $title,
        string $body = ''
    ): void {
        foreach (array_unique($userIds) as $userId) {
            $this->dfdb->table($this->dfdb->prefix . 'content_notification')->insert([
                'notification_id' => Ulid::generateAsString(),
                'content_id' => $contentId,
                'user_id' => $userId,
                'activity_id' => $activityId,
                'notification_type' => $type,
                'title' => $title,
                'body' => $body,
                'is_read' => 0,
                'created_at' => $this->now(),
                'read_at' => null,
            ]);
        }
    }

    private function assertCan(string $permission): void
    {
        if (false === current_user_can(perm: $permission)) {
            throw new \RuntimeException('Access denied.');
        }
    }

    private function now(): string
    {
        return QubusDateTimeImmutable::now('GMT')->toDateTimeString();
    }
}
