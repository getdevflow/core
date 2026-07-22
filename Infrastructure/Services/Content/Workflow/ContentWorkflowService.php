<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Content\Workflow;

use App\Domain\Content\Command\ContentWorkflowUpdateCommand;
use App\Domain\Content\ValueObject\ContentId;
use App\Infrastructure\Services\Content\Workflow\Queue\ContentWorkflowEmailNotification;
use App\Infrastructure\Services\Trait\RevisionEventTypeAware;
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

use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\content_workflow_activity_label;
use function App\Shared\Helpers\current_user_can;
use function App\Shared\Helpers\get_current_user_id;
use function App\Shared\Helpers\get_option;
use function App\Shared\Helpers\is_super_admin;
use function Codefy\Framework\Helpers\command;
use function Codefy\Framework\Helpers\logger;
use function Codefy\Framework\Helpers\queue;
use function Codefy\Framework\Helpers\trans_html;
use function json_decode;
use function Qubus\Security\Helpers\esc_html;
use function Qubus\Security\Helpers\purify_html;
use function Qubus\Support\Helpers\is_false__;
use function trim;

final readonly class ContentWorkflowService
{
    use RevisionEventTypeAware;

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
            $incomingReviewers = $reviewers;
            $reviewers = array_values(array_unique(array_filter($reviewers)));

            if ($reviewers === []) {
                $reviewers = $this->defaultEditorialUserIds(excludeUserId: $userId);
            }

            $existingReviewerStatus = $attribute['workflow']['reviewer_status'] ?? [];

            $attribute['workflow'] = array_merge($attribute['workflow'] ?? [], [
                'stage' => 'in_review',
                'reviewers' => $reviewers,
                'reviewer_status' => $this->normalizeReviewerStatus(
                    reviewers: $reviewers,
                    existing: is_array($existingReviewerStatus) ? $existingReviewerStatus : []
                ),
                'approval_required' => true,
                'review_ready' => false,
            ]);

            $this->updateContent($contentId, 'pending', $attribute);

            $activityId = $this->log(
                contentId: $contentId,
                userId: $userId,
                type: 'review_requested',
                fromStatus: (string) $content['content_status'],
                toStatus: 'pending',
                message: $message,
                metadata: [
                    'reviewers' => $reviewers,
                    'auto_assigned_reviewers' => $incomingReviewers === [],
                ]
            );

            $this->notifyMany(
                contentId: $contentId,
                userIds: $reviewers,
                activityId: $activityId,
                type: 'review_requested',
                title: trans_html('Content ready for review'),
                body: $message !== '' ? $message : trans_html('A content item is ready for review.')
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
     * @param array $reviewers
     * @return array
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function assignReviewers(
        string $contentId,
        string $userId,
        array $reviewers
    ): array {
        $this->assertCan('assign:content_reviewers');

        return $this->dfdb->transactional(function () use ($contentId, $userId, $reviewers): array {
            $content = $this->content($contentId);
            $attribute = $this->attribute($content['content_attribute'] ?? null);

            $reviewers = array_values(array_unique(array_filter($reviewers)));
            $existingReviewerStatus = $attribute['workflow']['reviewer_status'] ?? [];

            $attribute['workflow'] = array_merge($attribute['workflow'] ?? [], [
                'reviewers' => $reviewers,
                'reviewer_status' => $this->normalizeReviewerStatus(
                    reviewers: $reviewers,
                    existing: is_array($existingReviewerStatus) ? $existingReviewerStatus : []
                ),
            ]);

            unset($attribute['workflow']['assigned_to']);

            $this->updateContent(
                contentId: $contentId,
                status: (string) $content['content_status'],
                attribute: $attribute
            );

            $activityId = $this->log(
                contentId: $contentId,
                userId: $userId,
                type: 'reviewers_assigned',
                message: '',
                metadata: [
                    'reviewers' => $reviewers,
                ]
            );

            if ($reviewers !== []) {
                $this->notifyMany(
                    contentId: $contentId,
                    userIds: $reviewers,
                    activityId: $activityId,
                    type: 'reviewers_assigned',
                    title: trans_html('Review assignment updated'),
                    body: trans_html('You have been assigned to review content.')
                );
            }

            return [
                'activity_id' => $activityId,
                'reviewers' => $reviewers,
                'reviewer_names' => $this->reviewerNames($reviewers),
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
            $workflow = $attribute['workflow'] ?? [];

            $this->assertContentStatus($content, 'pending');
            $this->assertWorkflowStage($workflow, 'in_review');

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
        $this->assertCan('approve:content');

        return $this->dfdb->transactional(function () use ($contentId, $userId, $message): array {
            $content = $this->content($contentId);
            $attribute = $this->attribute($content['content_attribute'] ?? null);
            $workflow = $attribute['workflow'] ?? [];

            $this->assertContentStatus($content, 'pending');
            $this->assertWorkflowStage($workflow, 'in_review');

            $attribute['workflow'] = array_merge($workflow, [
                'stage' => 'changes_requested',
                'review_ready' => false,
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
            $workflow = $attribute['workflow'] ?? [];

            if (false === is_super_admin()) {
                $this->assertContentStatus($content, ['pending', 'scheduled']);
                $this->assertWorkflowStage($workflow, 'approved');
            }

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
    public function completeReview(string $contentId, string $userId, string $message = ''): array
    {
        $this->assertCan('review:content');

        return $this->dfdb->transactional(function () use ($contentId, $userId, $message): array {
            $content = $this->content($contentId);
            $attribute = $this->attribute($content['content_attribute'] ?? null);
            $workflow = $attribute['workflow'] ?? [];

            $this->assertContentStatus($content, 'pending');
            $this->assertWorkflowStage($workflow, 'in_review');

            $reviewers = $workflow['reviewers'] ?? [];

            if (! in_array($userId, $reviewers, true) && false === current_user_can(perm: 'approve:content')) {
                throw new RuntimeException(trans_html('You are not assigned as a reviewer.'));
            }

            $reviewerStatus = $workflow['reviewer_status'] ?? [];
            $reviewerStatus[$userId] = [
                'status' => 'complete',
                'completed_at' => $this->now(),
            ];

            $allComplete = $this->allReviewersComplete(
                reviewers: $reviewers,
                reviewerStatus: $reviewerStatus
            );

            $attribute['workflow'] = array_merge($workflow, [
                'reviewer_status' => $reviewerStatus,
                'review_ready' => $allComplete,
            ]);

            $this->updateContent(
                contentId: $contentId,
                status: (string) $content['content_status'],
                attribute: $attribute
            );

            $activityId = $this->log(
                contentId: $contentId,
                userId: $userId,
                type: 'review_completed',
                message: $message,
                metadata: [
                    'reviewer_id' => $userId,
                ]
            );

            if ($allComplete) {
                $readyActivityId = $this->log(
                    contentId: $contentId,
                    userId: $userId,
                    type: 'review_ready',
                    message: trans_html('All assigned reviewers have completed their review.'),
                    metadata: [
                        'reviewers' => $reviewers,
                    ]
                );

                $approvers = $this->defaultEditorialUserIds(excludeUserId: $userId);

                $this->notifyMany(
                    contentId: $contentId,
                    userIds: $approvers,
                    activityId: $readyActivityId,
                    type: 'review_ready',
                    title: trans_html('Content ready for approval'),
                    body: trans_html('All assigned reviewers have completed their review.')
                );
            }

            return [
                'activity_id' => $activityId,
                'stage' => $workflow['stage'] ?? 'in_review',
                'status' => (string) $content['content_status'],
                'reviewer_status' => $reviewerStatus,
                'review_ready' => $allComplete,
            ];
        });
    }

    /**
     * @return array
     * @throws ReflectionException
     * @throws \Qubus\Exception\Exception
     */
    public function reviewerCandidates(): array
    {
        $results = $this->dfdb->getResults(
            query: "SELECT user_id, user_attribute"
            . " FROM {$this->dfdb->basePrefix}site_user",
            output: Database::ARRAY_A
        );

        if(is_false__($results)) {
            return [];
        }

        $ids = [];

        foreach ($results as $row) {
            $json = json_decode($row['user_attribute'], true);

            if (
                    ($json['role'] ?? null) === 'super'
                    || ($json['role'] ?? null) === 'admin'
                    || ($json['role'] ?? null) === 'editor'
            ) {
                if ((string) $row['user_id'] !== (string) get_current_user_id()) {
                    $ids[] = $row['user_id'];
                }
            }
        }

        if ($ids === []) {
            return [];
        }

        return $this->dfdb
            ->select('user_id, user_fname, user_lname, user_email')
            ->table($this->dfdb->basePrefix . 'user')
            ->whereIn('user_id', $ids)
            ->find(callback: static fn(array $rows): array => $rows);
    }

    public function reviewerNames(array $reviewerIds, array $reviewerStatus = []): array
    {
        $reviewerIds = array_values(array_unique(array_filter($reviewerIds)));

        if ($reviewerIds === []) {
            return [];
        }

        $rows = $this->dfdb
            ->select('user_id, user_fname, user_lname, user_email, user_login')
            ->table($this->dfdb->basePrefix . 'user')
            ->whereIn('user_id', $reviewerIds)
            ->find(callback: static fn(array $rows): array => $rows);

        return array_map(static function (array $row) use ($reviewerStatus): array {
            $id = (string) $row['user_id'];

            $name = trim(
                (string) ($row['user_fname'] ?? '') . ' ' .
                (string) ($row['user_lname'] ?? '')
            );

            return [
                'id' => $id,
                'name' => $name !== ''
                    ? $name
                    : (string) ($row['user_login'] ?? $row['user_email'] ?? 'Unknown User'),
                'email' => (string) ($row['user_email'] ?? ''),
                'status' => (string) ($reviewerStatus[$id]['status'] ?? 'pending'),
                'completed_at' => $reviewerStatus[$id]['completed_at'] ?? null,
            ];
        }, $rows);
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
            throw new RuntimeException(trans_html('Comment cannot be empty.'));
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

        $this->notifyCommentUsers(
            contentId: $contentId,
            actorUserId: $userId,
            activityId: $activityId,
            body: $body,
            parentId: $parentId
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
            $authorId = trim((string) ($content['content_author'] ?? ''));

            if ($authorId === '') {
                throw new \RuntimeException(
                    sprintf('Scheduled content %s has no valid author.', $contentId)
                );
            }

            if ($content['content_status'] !== 'scheduled') {
                throw new \RuntimeException(trans_html('Content is not scheduled.'));
            }

            if (strtotime((string) $content['content_published_gmt']) > time()) {
                throw new \RuntimeException(trans_html('Scheduled publish date has not passed.'));
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
                userId: $authorId,
                type: 'scheduled_published',
                fromStatus: 'scheduled',
                toStatus: 'published',
                message: trans_html('Scheduled content was published automatically.'),
                metadata: [
                    'triggered_by' => 'system',
                    'scheduled_job' => true,
                ]
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
    public function withdrawReview(string $contentId, string $userId, string $message = ''): array
    {
        $this->assertCan('review:content');

        return $this->dfdb->transactional(function () use ($contentId, $userId, $message): array {
            $content = $this->content($contentId);
            $attribute = $this->attribute($content['content_attribute'] ?? null);
            $workflow = $attribute['workflow'] ?? [];

            $this->assertContentStatus($content, 'pending');
            $this->assertWorkflowStage($workflow, 'in_review');

            $attribute['workflow'] = array_merge($workflow, [
                'stage' => 'draft',
                'review_ready' => false,
            ]);

            $this->updateContent($contentId, 'draft', $attribute);

            $activityId = $this->log(
                contentId: $contentId,
                userId: $userId,
                type: 'review_withdrawn',
                fromStatus: (string) $content['content_status'],
                toStatus: 'draft',
                message: $message
            );

            return [
                'activity_id' => $activityId,
                'status' => 'draft',
                'stage' => 'draft',
            ];
        });
    }

    /**
     * @param string $contentId
     * @param int $limit
     * @return array
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     */
    public function activity(string $contentId, int $limit = 25): array
    {
        $this->assertCan('view:content_activity');

        $rows = $this->dfdb
            ->table($this->dfdb->prefix . 'content_workflow_activity', 'a')
            ->select(['a.*', 'u.user_fname', 'u.user_lname', 'u.user_login', 'u.user_email'])
            ->join($this->dfdb->basePrefix . 'user', 'u.user_id = a.user_id', 'u')
            ->where('a.content_id', $contentId)
            ->orderBy('a.created_at', 'DESC')
            ->limit($limit)
            ->find(callback: static fn(array $rows): array => $rows);

        return array_map(static function (array $row): array {
            $firstName = $row['user_fname'] ? esc_html($row['user_fname']) : '';
            $lastName = $row['user_lname'] ? esc_html($row['user_lname']) : '';
            $login = $row['user_login'] ? esc_html($row['user_login']) : '';
            $email = $row['user_email'] ? esc_html($row['user_email']) : '';

            $actor = trim($firstName . ' ' . $lastName);

            if ($actor === '') {
                $actor = $login !== '' ? $login : $email;
            }

            if ($actor === '' || (string) ($row['user_id'] ?? '') === 'system') {
                $actor = trans_html('System');
            }

            $metadata = json_decode($row['metadata'] ? purify_html($row['metadata']) : '{}', true);

            if (! is_array($metadata)) {
                $metadata = [];
            }

            $action = content_workflow_activity_label(esc_html($row['activity_type']));

            $row['actor'] = $actor;
            $row['action'] = $action;
            $row['label'] = trim($actor . ' ' . $action);
            $row['metadata'] = $metadata;

            return $row;
        }, $rows);
    }

    /**
     * @param string $contentId
     * @param string $status
     * @return array
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     */
    public function comments(string $contentId, string $status = 'open'): array
    {
        $this->assertCan('view:content_comments');

        $query = $this->dfdb
            ->table($this->dfdb->prefix . 'content_comment')
            ->where('content_id', $contentId);

        if (in_array($status, ['open', 'resolved'], true)) {
            $query->where('comment_status', $status);
        }

        $rows = $query
            ->orderBy('created_at', 'DESC')
            ->find(callback: static fn(array $rows): array => $rows);

        return $this->nestComments($rows);
    }

    /**
     * @param string $contentId
     * @return array
     * @throws \Qubus\Exception\Exception
     */
    private function content(string $contentId): array
    {
        $row = $this->dfdb
            ->table($this->dfdb->prefix . 'content')
            ->where('content_id', $contentId)
            ->findOne();

        if ($row === false) {
            throw new \RuntimeException(trans_html('Content not found.'));
        }

        return $row->toArray();
    }

    /**
     * @throws JsonException
     */
    private function attribute(?string $json = null): array
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
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     */
    private function updateContent(string $contentId, string $status, array $attribute): void
    {
        try {
            command(
                command: new ContentWorkflowUpdateCommand([
                    'id' => ContentId::fromString($contentId),
                    'attribute' => ArrayLiteral::fromNative($attribute),
                    'status' => StringLiteral::fromNative($status),
                    'modified' => QubusDateTimeImmutable::now(),
                    'modifiedGmt' => QubusDateTimeImmutable::now('UTC'),
                ])
            );
        } catch (UnresolvableCommandHandlerException | ReflectionException | CommandPropertyNotFoundException $e) {
            logger('error', $e->getMessage());

            throw new RuntimeException(trans_html('Content workflow update failed.'), previous: $e);
        }
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
            throw new RuntimeException(trans_html('Comment cannot be empty.'));
        }

        $comment = $this->commentById($commentId);

        if (
                (string) $comment['user_id'] !== $userId &&
                false === current_user_can(perm: 'edit:content_comments')
        ) {
            throw new RuntimeException(trans_html('Access denied.'));
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
            throw new RuntimeException(trans_html('Parent comment does not belong to this content.'));
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
        $now = $this->now();

        $this->dfdb
            ->table($this->dfdb->prefix . 'content_comment')
            ->where('comment_id', $commentId)
            ->update([
                'comment_status' => 'resolved',
                'updated_at' => $now,
            ]);

        $resolvedChildren = $this->resolveOpenChildComments($commentId, $now);

        $this->log(
            contentId: (string) $comment['content_id'],
            userId: $userId,
            type: 'comment_resolved',
            metadata: [
                'comment_id' => $commentId,
                'resolved_children' => $resolvedChildren,
            ]
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

        if (false === current_user_can(perm: 'delete:content_comments')) {
            throw new RuntimeException(trans_html('Access denied.'));
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

    /**
     * @param string $contentId
     * @return int[]
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     */
    public function commentSummary(string $contentId): array
    {
        $this->assertCan('view:content_comments');

        $rows = $this->dfdb->raw(
            sprintf(
                "SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN comment_status = 'open' THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN comment_status = 'resolved' THEN 1 ELSE 0 END) AS resolved_count
                FROM %scontent_comment
                WHERE content_id = ?",
                $this->dfdb->prefix
            ),
            [$contentId]
        );

        $row = $rows[0] ?? [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'open' => (int) ($row['open_count'] ?? 0),
            'resolved' => (int) ($row['resolved_count'] ?? 0),
        ];
    }

    private function resolveOpenChildComments(string $parentId, string $resolvedAt): int
    {
        $children = $this->dfdb
            ->table($this->dfdb->prefix . 'content_comment')
            ->where('parent_id', $parentId)
            ->where('comment_status', 'open')
            ->find(callback: static fn(array $rows): array => $rows);

        $resolved = 0;

        foreach ($children as $child) {
            $childId = (string) $child['comment_id'];

            $this->dfdb
                ->table($this->dfdb->prefix . 'content_comment')
                ->where('comment_id', $childId)
                ->update([
                    'comment_status' => 'resolved',
                    'updated_at' => $resolvedAt,
                ]);

            $resolved++;
            $resolved += $this->resolveOpenChildComments($childId, $resolvedAt);
        }

        return $resolved;
    }

    /**
     * @param string $commentId
     * @return array
     * @throws \Qubus\Exception\Exception
     */
    private function commentById(string $commentId): array
    {
        $row = $this->dfdb
            ->table($this->dfdb->prefix . 'content_comment')
            ->where('comment_id', $commentId)
            ->findOne();

        if ($row === false) {
            throw new RuntimeException(trans_html('Comment not found.'));
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

    /**
     * @throws ReflectionException
     * @throws InvalidArgumentException
     * @throws \Qubus\Exception\Exception
     */
    private function notifyMany(
        string $contentId,
        array $userIds,
        string $activityId,
        string $type,
        string $title,
        string $body = ''
    ): void {
        $userIds = array_values(array_unique(array_filter($userIds)));

        if ($userIds === []) {
            return;
        }

        foreach ($userIds as $userId) {
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

        $anchor = str_starts_with($type, 'comment_')
            ? 'editorial-comments-box'
            : 'content-workflow-box';

        $this->queueEmailNotifications(
            contentId: $contentId,
            userIds: $userIds,
            title: $title,
            message: $body !== '' ? $body : $title,
            type: str_starts_with($type, 'comment_') ? trans_html('Editorial Comment') : trans_html('Editorial Workflow'),
            anchor: $anchor
        );
    }

    private function defaultEditorialUserIds(?string $excludeUserId = null): array
    {
        $results = $this->dfdb->getResults(
            query: "SELECT user_id, user_attribute
            FROM {$this->dfdb->basePrefix}site_user",
            output: Database::ARRAY_A
        );

        if (is_false__($results)) {
            return [];
        }

        $ids = [];

        foreach ($results as $row) {
            $json = json_decode((string) ($row['user_attribute'] ?? '{}'), true);

            if (! is_array($json)) {
                continue;
            }

            $role = $json['role'] ?? null;

            if (! in_array($role, ['super', 'admin', 'editor'], true)) {
                continue;
            }

            if ($excludeUserId !== null && (string) $row['user_id'] === $excludeUserId) {
                continue;
            }

            $ids[] = (string) $row['user_id'];
        }

        return array_values(array_unique($ids));
    }

    private function normalizeReviewerStatus(array $reviewers, array $existing = []): array
    {
        $status = [];

        foreach ($reviewers as $reviewerId) {
            $reviewerId = (string) $reviewerId;

            $status[$reviewerId] = $existing[$reviewerId] ?? [
                'status' => 'pending',
                'completed_at' => null,
            ];
        }

        return $status;
    }

    private function allReviewersComplete(array $reviewers, array $reviewerStatus): bool
    {
        if ($reviewers === []) {
            return false;
        }

        foreach ($reviewers as $reviewerId) {
            $reviewerId = (string) $reviewerId;

            if (($reviewerStatus[$reviewerId]['status'] ?? 'pending') !== 'complete') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array $workflow
     * @param array|string $allowedStages
     * @return void
     * @throws \Qubus\Exception\Exception
     */
    private function assertWorkflowStage(array $workflow, array|string $allowedStages): void
    {
        $allowedStages = (array) $allowedStages;
        $currentStage = (string) ($workflow['stage'] ?? '');

        if (! in_array($currentStage, $allowedStages, true)) {
            throw new RuntimeException(sprintf(
                trans_html('Invalid workflow stage. Expected one of [%s], current stage is [%s].'),
                implode(', ', $allowedStages),
                $currentStage !== '' ? $currentStage : 'none'
            ));
        }
    }

    /**
     * @param array $content
     * @param array|string $allowedStatuses
     * @return void
     * @throws \Qubus\Exception\Exception
     */
    private function assertContentStatus(array $content, array|string $allowedStatuses): void
    {
        $allowedStatuses = (array) $allowedStatuses;
        $currentStatus = (string) ($content['content_status'] ?? '');

        if (! in_array($currentStatus, $allowedStatuses, true)) {
            throw new RuntimeException(sprintf(
                trans_html('Invalid content status. Expected one of [%s], current status is [%s].'),
                implode(', ', $allowedStatuses),
                $currentStatus !== '' ? $currentStatus : 'none'
            ));
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
            throw new \RuntimeException(trans_html('Access denied.'));
        }
    }

    private function now(): string
    {
        return QubusDateTimeImmutable::now('GMT')->toDateTimeString();
    }

    /**
     * @param string $contentId
     * @param array $userIds
     * @param string $title
     * @param string $message
     * @param string $type
     * @param string $anchor
     * @return void
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws \Qubus\Exception\Exception
     */
    private function queueEmailNotifications(
        string $contentId,
        array $userIds,
        string $title,
        string $message,
        string $type = 'Editorial Workflow',
        string $anchor = 'content-workflow-box'
    ): void {
        $userIds = array_values(array_unique(array_filter($userIds)));

        if ($userIds === []) {
            return;
        }

        $content = $this->content($contentId);

        $users = $this->dfdb
            ->select('user_id, user_fname, user_lname, user_email, user_login')
            ->table($this->dfdb->basePrefix . 'user')
            ->whereIn('user_id', $userIds)
            ->find(callback: static fn(array $rows): array => $rows);

        $url = admin_url(
            path: 'content-type/' .
            (string) ($content['content_type'] ?? 'post') .
            '/' .
            $contentId
        ) . '/#' .
        $anchor;

        foreach ($users as $user) {
            $email = (string) ($user['user_email'] ?? '');

            if ($email === '') {
                continue;
            }

            $name = trim(
                (string) ($user['user_fname'] ?? '') . ' ' .
                (string) ($user['user_lname'] ?? '')
            );

            if ($name === '') {
                $name = (string) ($user['user_login'] ?? $email);
            }

            queue(
                new ContentWorkflowEmailNotification([
                    'email' => esc_html($email),
                    'user' => esc_html($name),
                    'sitename' => (string) get_option('sitename'),
                    'notification_type' => $type,
                    'notification_title' => $title,
                    'notification_message' => '<p>' . purify_html($message) . '</p>',
                    'action_url' => $url,
                    'action_label' => trans_html('View Content'),
                ])
            )->createItem();
        }
    }

    /**
     * @param string $contentId
     * @param string $actorUserId
     * @param string $activityId
     * @param string $body
     * @param string|null $parentId
     * @return void
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws ReflectionException
     * @throws \Qubus\Exception\Exception
     */
    private function notifyCommentUsers(
        string $contentId,
        string $actorUserId,
        string $activityId,
        string $body,
        ?string $parentId = null
    ): void {
        $content = $this->content($contentId);
        $attribute = $this->attribute(
            $content['content_attribute'] ?? null
        );
        $workflow = $attribute['workflow'] ?? [];

        $userIds = [];

        if (! empty($content['content_author'])) {
            $userIds[] = (string) $content['content_author'];
        }

        foreach (($workflow['reviewers'] ?? []) as $reviewerId) {
            $userIds[] = (string) $reviewerId;
        }

        if ($parentId !== null && $parentId !== '') {
            $parent = $this->commentById($parentId);
            $userIds[] = (string) $parent['user_id'];
        }

        $userIds = array_values(array_unique(array_filter(
            $userIds,
            static fn(string $id): bool => $id !== $actorUserId
        )));

        if ($userIds === []) {
            return;
        }

        $isReply = $parentId !== null && $parentId !== '';

        $this->notifyMany(
            contentId: $contentId,
            userIds: $userIds,
            activityId: $activityId,
            type: $isReply ? 'comment_replied' : 'comment_added',
            title: $isReply ? trans_html('New editorial comment reply') : trans_html('New editorial comment'),
            body: $body
        );
    }
}
