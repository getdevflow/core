<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Content\Workflow;

use App\Domain\Content\Command\ContentWorkflowUpdateCommand;
use App\Domain\Content\ValueObject\ContentId;
use App\Shared\ValueObject\ArrayLiteral;
use Codefy\CommandBus\Exceptions\CommandPropertyNotFoundException;
use Codefy\CommandBus\Exceptions\UnresolvableCommandHandlerException;
use Exception;
use JsonException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Expressive\Database;
use Qubus\Support\DateTime\QubusDateTimeImmutable;
use Qubus\ValueObjects\Identity\Ulid;
use Qubus\ValueObjects\StringLiteral\StringLiteral;
use ReflectionException;
use RuntimeException;

use function App\Shared\Helpers\current_user_can;
use function Codefy\Framework\Helpers\command;

final readonly class ContentWorkflowService
{
    public function __construct(private Database $dfdb)
    {
    }

    /**
     * @param string $contentId
     * @param string $userId
     * @param array $reviewers
     * @param string $message
     * @return array
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
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
     * @param string $contentId
     * @param string $userId
     * @param string $message
     * @return array
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
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
     * @param string $contentId
     * @param string $userId
     * @param string $message
     * @return array
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
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
     * @param string $contentId
     * @param string $userId
     * @param string $message
     * @return array
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
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
     * @param string $contentId
     * @param string $userId
     * @param string $body
     * @param string|null $parentId
     * @param array|null $selection
     * @param string $type
     * @return array
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     */
    public function comment(
        string $contentId,
        string $userId,
        string $body,
        ?string $parentId = null,
        ?array $selection = null,
        string $type = 'editorial'
    ): array {
        $this->assertCan('comment:content');

        if (trim($body) === '') {
            throw new RuntimeException('Comment cannot be empty.');
        }

        $commentId = Ulid::generateAsString();

        $this->dfdb->table($this->dfdb->prefix . 'content_comment')->insert([
            'comment_id' => $commentId,
            'content_id' => $contentId,
            'parent_id' => $parentId,
            'user_id' => $userId,
            'comment_body' => $body,
            'comment_status' => 'open',
            'comment_type' => $type,
            'selection_json' => $selection !== null ? json_encode($selection, JSON_THROW_ON_ERROR) : null,
            'created_at' => $this->now(),
            'updated_at' => null,
        ]);

        $activityId = $this->log(
            contentId: $contentId,
            userId: $userId,
            type: $parentId === null ? 'comment_added' : 'comment_replied',
            message: $body,
            metadata: [
                'comment_id' => $commentId,
                'parent_id' => $parentId,
                'comment_type' => $type,
            ]
        );

        return [
            'comment_id' => $commentId,
            'activity_id' => $activityId,
        ];
    }

    /**
     * @param string $contentId
     * @return array
     * @throws Exception
     */
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

    /**
     * @param string $contentId
     * @return array
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     */
    public function comments(string $contentId): array
    {
        $this->assertCan('view:content_comments');

        $rows = $this->dfdb
            ->table($this->dfdb->prefix . 'content_comment')
            ->where('content_id', $contentId)
            ->orderBy('created_at', 'DESC')
            ->find(callback: static fn(array $rows): array => $rows);

        return $this->nestComments($rows);
    }

    public function revisions(string $contentId, int $limit = 25): array
    {
        return $this->dfdb
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
     * @param string $contentId
     * @param string $status
     * @param array $attribute
     * @throws ReflectionException
     * @throws TypeException
     * @throws CommandPropertyNotFoundException
     * @throws UnresolvableCommandHandlerException
     */
    private function updateContent(string $contentId, string $status, array $attribute): void
    {
        command(
            command: new ContentWorkflowUpdateCommand([
                'id' => ContentId::fromString($contentId),
                'attribute' => ArrayLiteral::fromNative($attribute),
                'status' => StringLiteral::fromNative($status),
                'modified' => QubusDateTimeImmutable::now(),
                'modifiedGmt' => QubusDateTimeImmutable::now('GMT'),
            ])
        );
    }

    /**
     * @param string $commentId
     * @param string $userId
     * @param string $body
     * @return array
     * @throws JsonException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     * @throws ReflectionException
     */
    public function updateComment(string $commentId, string $userId, string $body): array
    {
        if (trim($body) === '') {
            throw new RuntimeException('Comment cannot be empty.');
        }

        $comment = $this->commentById($commentId);

        if (
                (string) $comment['user_id'] !== $userId &&
                false === current_user_can(perm: 'edit:content_comments')
        ) {
            throw new RuntimeException('Access denied.');
        }

        $updatedAt = $this->now();

        $this->dfdb
            ->table($this->dfdb->prefix . 'content_comment')
            ->where('comment_id', $commentId)
            ->update([
                'comment_body' => $body,
                'updated_at' => $updatedAt,
            ]);

        $this->log(
            contentId: (string) $comment['content_id'],
            userId: $userId,
            type: 'comment_updated',
            message: $body,
            metadata: ['comment_id' => $commentId]
        );

        return [
            'comment_id' => $commentId,
            'body' => $body,
            'updated_at' => $updatedAt,
        ];
    }

    /**
     * @param string $contentId
     * @param string $userId
     * @param string $parentId
     * @param string $body
     * @return array
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     */
    public function replyToComment(
        string $contentId,
        string $userId,
        string $parentId,
        string $body
    ): array {
        $this->assertCan('reply:content_comments');

        $parent = $this->commentById($parentId);

        if ((string) $parent['content_id'] !== $contentId) {
            throw new RuntimeException('Parent comment does not belong to this content.');
        }

        return $this->comment(
            contentId: $contentId,
            userId: $userId,
            body: $body,
            parentId: $parentId,
            selection: null,
            type: 'reply'
        );
    }

    /**
     * @param string $commentId
     * @param string $userId
     * @return void
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     */
    public function resolveComment(string $commentId, string $userId): void
    {
        $this->assertCan('resolve:content_comments');

        $comment = $this->commentById($commentId);

        $this->dfdb
            ->table($this->dfdb->prefix . 'content_comment')
            ->where('comment_id', $commentId)
            ->update([
                'comment_status' => 'resolved',
                'updated_at' => $this->now(),
            ]);

        $this->log(
            contentId: (string) $comment['content_id'],
            userId: $userId,
            type: 'comment_resolved',
            metadata: ['comment_id' => $commentId]
        );
    }

    /**
     * @param string $commentId
     * @param string $userId
     * @return void
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     */
    public function reopenComment(string $commentId, string $userId): void
    {
        $this->assertCan('resolve:content_comments');

        $comment = $this->commentById($commentId);

        $this->dfdb
            ->table($this->dfdb->prefix . 'content_comment')
            ->where('comment_id', $commentId)
            ->update([
                'comment_status' => 'open',
                'updated_at' => $this->now(),
            ]);

        $this->log(
            contentId: (string) $comment['content_id'],
            userId: $userId,
            type: 'comment_reopened',
            metadata: ['comment_id' => $commentId]
        );
    }

    /**
     * @param string $commentId
     * @param string $userId
     * @return void
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     */
    public function deleteComment(string $commentId, string $userId): void
    {
        $comment = $this->commentById($commentId);

        if (
                (string) $comment['user_id'] !== $userId &&
                false === current_user_can(perm: 'delete:content_comments')
        ) {
            throw new RuntimeException('Access denied.');
        }

        $this->dfdb
            ->table($this->dfdb->prefix . 'content_comment')
            ->where('comment_id', $commentId)
            ->delete();

        $this->log(
            contentId: (string) $comment['content_id'],
            userId: $userId,
            type: 'comment_deleted',
            metadata: ['comment_id' => $commentId]
        );
    }

    private function commentById(string $commentId): array
    {
        $row = $this->dfdb
            ->table($this->dfdb->prefix . 'content_comment')
            ->where('comment_id', $commentId)
            ->findOne();

        if ($row === false) {
            throw new RuntimeException('Comment not found.');
        }

        return $row->toArray();
    }

    private function nestComments(array $rows): array
    {
        $comments = [];
        $tree = [];

        foreach ($rows as $row) {
            $row['replies'] = [];
            $comments[$row['comment_id']] = $row;
        }

        foreach ($comments as $id => &$comment) {
            if (! empty($comment['parent_id']) && isset($comments[$comment['parent_id']])) {
                $comments[$comment['parent_id']]['replies'][] = &$comment;
                continue;
            }

            $tree[] = &$comment;
        }

        unset($comment);

        return $tree;
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

    /**
     * @param string $permission
     * @return void
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     */
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
