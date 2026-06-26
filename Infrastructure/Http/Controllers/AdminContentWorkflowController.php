<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Infrastructure\Services\Content\Workflow\ContentRevisionDiffService;
use App\Infrastructure\Services\Content\Workflow\ContentRevisionService;
use App\Infrastructure\Services\Content\Workflow\ContentWorkflowService;
use Codefy\Framework\Http\BaseController;
use JsonException;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Http\ServerRequest;
use ReflectionException;

use function App\Shared\Helpers\current_user_can;
use function App\Shared\Helpers\get_current_user_id;
use function Codefy\Framework\Helpers\trans_html;

final class AdminContentWorkflowController extends BaseController
{
    /**
     * @param ServerRequest $request
     * @param ContentWorkflowService $workflow
     * @param string $contentId
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     */
    public function requestReview(
        ServerRequest $request,
        ContentWorkflowService $workflow,
        string $contentId
    ): ResponseInterface {
        $body = $request->getParsedBody();

        return new JsonResponse([
            'success' => true,
            'workflow' => $workflow->requestReview(
                contentId: $contentId,
                userId: get_current_user_id(),
                reviewers: (array) ($body['reviewers'] ?? []),
                message: (string) ($body['message'] ?? '')
            ),
        ]);
    }

    /**
     * @param ServerRequest $request
     * @param ContentWorkflowService $workflow
     * @param string $contentId
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function assignReviewers(
        ServerRequest $request,
        ContentWorkflowService $workflow,
        string $contentId
    ): ResponseInterface {
        $body = $request->getParsedBody();

        return new JsonResponse([
            'success' => true,
            'workflow' => $workflow->assignReviewers(
                contentId: $contentId,
                userId: get_current_user_id(),
                reviewers: (array) ($body['reviewers'] ?? [])
            ),
            'message' => trans_html('Reviewers updated.'),
        ]);
    }

    /**
     * @param ServerRequest $request
     * @param ContentWorkflowService $workflow
     * @param string $contentId
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function completeReview(
        ServerRequest $request,
        ContentWorkflowService $workflow,
        string $contentId
    ): ResponseInterface {
        return new JsonResponse([
            'success' => true,
            'workflow' => $workflow->completeReview(
                contentId: $contentId,
                userId: get_current_user_id(),
                message: (string) (($request->getParsedBody()['message'] ?? ''))
            ),
            'message' => trans_html('Review marked complete.'),
        ]);
    }

    /**
     * @param ServerRequest $request
     * @param ContentWorkflowService $workflow
     * @param string $contentId
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     */
    public function withdrawReview(
        ServerRequest $request,
        ContentWorkflowService $workflow,
        string $contentId
    ): ResponseInterface {
        return new JsonResponse([
            'success' => true,
            'workflow' => $workflow->withdrawReview(
                contentId: $contentId,
                userId: get_current_user_id(),
                message: (string) (($request->getParsedBody()['message'] ?? ''))
            ),
            'message' => trans_html('Review request withdrawn.'),
        ]);
    }

    /**
     * @param ServerRequest $request
     * @param ContentWorkflowService $workflow
     * @param string $contentId
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     */
    public function approve(
        ServerRequest $request,
        ContentWorkflowService $workflow,
        string $contentId
    ): ResponseInterface {
        return new JsonResponse([
            'success' => true,
            'workflow' => $workflow->approve(
                contentId: $contentId,
                userId: get_current_user_id(),
                message: (string) (($request->getParsedBody()['message'] ?? ''))
            ),
        ]);
    }

    /**
     * @param ServerRequest $request
     * @param ContentWorkflowService $workflow
     * @param string $contentId
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     */
    public function requestChanges(
        ServerRequest $request,
        ContentWorkflowService $workflow,
        string $contentId
    ): ResponseInterface {
        return new JsonResponse([
            'success' => true,
            'workflow' => $workflow->requestChanges(
                contentId: $contentId,
                userId: get_current_user_id(),
                message: (string) (($request->getParsedBody()['message'] ?? ''))
            ),
        ]);
    }

    /**
     * @param ServerRequest $request
     * @param ContentWorkflowService $workflow
     * @param string $contentId
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     */
    public function publish(
        ServerRequest $request,
        ContentWorkflowService $workflow,
        string $contentId
    ): ResponseInterface {
        return new JsonResponse([
            'success' => true,
            'workflow' => $workflow->publish(
                contentId: $contentId,
                userId: get_current_user_id(),
                message: (string) (($request->getParsedBody()['message'] ?? ''))
            ),
        ]);
    }

    /**
     * @param ServerRequest $request
     * @param ContentWorkflowService $workflow
     * @param string $contentId
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     */
    public function comment(
        ServerRequest $request,
        ContentWorkflowService $workflow,
        string $contentId
    ): ResponseInterface {
        $body = $request->getParsedBody();
        $parentId = (string) ($body['parent_id'] ?? '');

        return new JsonResponse([
            'success' => true,
            'comment' => $parentId !== ''
                ? $workflow->replyToComment(
                    contentId: $contentId,
                    userId: get_current_user_id(),
                    parentId: $parentId,
                    body: (string) ($body['comment'] ?? '')
                )
                : $workflow->comment(
                    contentId: $contentId,
                    userId: get_current_user_id(),
                    body: (string) ($body['comment'] ?? ''),
                    parentId: null,
                    selection: isset($body['selection']) && is_array($body['selection']) ? $body['selection'] : null,
                    type: 'editorial'
                ),
        ]);
    }

    /**
     * @param ServerRequest $request
     * @param ContentWorkflowService $workflow
     * @param string $contentId
     * @return ResponseInterface
     * @throws \JsonException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Qubus\Exception\Data\TypeException
     * @throws \Qubus\Exception\Exception
     * @throws \ReflectionException
     */
    public function updateComment(
        ServerRequest $request,
        ContentWorkflowService $workflow,
        string $contentId
    ): ResponseInterface {
        $body = $request->getParsedBody();

        return new JsonResponse([
            'success' => true,
            'comment' => $workflow->updateComment(
                commentId: (string) ($body['comment_id'] ?? ''),
                userId: get_current_user_id(),
                body: (string) ($body['comment'] ?? '')
            ),
        ]);
    }

    /**
     * @param ServerRequest $request
     * @param ContentWorkflowService $workflow
     * @param string $contentId
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     */
    public function replyComment(
        ServerRequest $request,
        ContentWorkflowService $workflow,
        string $contentId
    ): ResponseInterface {
        $body = $request->getParsedBody();

        return new JsonResponse([
            'success' => true,
            'comment' => $workflow->replyToComment(
                contentId: $contentId,
                userId: get_current_user_id(),
                parentId: (string) ($body['parent_id'] ?? ''),
                body: (string) ($body['comment'] ?? '')
            ),
        ]);
    }

    /**
     * @param ServerRequest $request
     * @param ContentWorkflowService $workflow
     * @param string $contentId
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     */
    public function resolveComment(
        ServerRequest $request,
        ContentWorkflowService $workflow,
        string $contentId
    ): ResponseInterface {
        $workflow->resolveComment(
            commentId: (string) ($request->getParsedBody()['comment_id'] ?? ''),
            userId: get_current_user_id(),
        );

        return new JsonResponse(['success' => true]);
    }

    /**
     * @param ServerRequest $request
     * @param ContentWorkflowService $workflow
     * @param string $contentId
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     */
    public function reopenComment(
        ServerRequest $request,
        ContentWorkflowService $workflow,
        string $contentId
    ): ResponseInterface {
        $workflow->reopenComment(
            commentId: (string) ($request->getParsedBody()['comment_id'] ?? ''),
            userId: get_current_user_id(),
        );

        return new JsonResponse(['success' => true]);
    }

    /**
     * @param ContentWorkflowService $workflow
     * @param string $contentId
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function commentSummary(ContentWorkflowService $workflow, string $contentId): ResponseInterface
    {
        return new JsonResponse([
            'success' => true,
            'summary' => $workflow->commentSummary($contentId),
        ]);
    }

    /**
     * @param ServerRequest $request
     * @param ContentWorkflowService $workflow
     * @param string $contentId
     * @return ResponseInterface
     * @throws \JsonException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Qubus\Exception\Data\TypeException
     * @throws \Qubus\Exception\Exception
     * @throws \ReflectionException
     */
    public function deleteComment(
        ServerRequest $request,
        ContentWorkflowService $workflow,
        string $contentId
    ): ResponseInterface {
        $workflow->deleteComment(
            commentId: (string) ($request->getParsedBody()['comment_id'] ?? ''),
            userId: get_current_user_id(),
        );

        return new JsonResponse(['success' => true]);
    }

    /**
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Qubus\Exception\Data\TypeException
     * @throws \Qubus\Exception\Exception
     * @throws \ReflectionException
     */
    private function permissions(): array
    {
        return [
            'can_view_comments' => current_user_can(perm: 'view:content_comments'),
            'can_comment' => current_user_can(perm: 'comment:content'),
            'can_reply_comment' => current_user_can(perm: 'reply:content_comments'),
            'can_edit_comment' => current_user_can(perm: 'edit:content_comments'),
            'can_resolve_comment' => current_user_can(perm: 'resolve:content_comments'),
            'can_delete_comment' => current_user_can(perm: 'delete:content_comments'),
            'can_restore_revision' => current_user_can(perm: 'restore:content_revisions'),
        ];
    }

    /**
     * @param ContentWorkflowService $workflow
     * @param string $contentId
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function activity(ContentWorkflowService $workflow, string $contentId): ResponseInterface
    {
        return new JsonResponse([
            'success' => true,
            'activity' => $workflow->activity($contentId),
        ]);
    }

    /**
     * @param ContentRevisionService $revisions
     * @param string $contentId
     * @return ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Qubus\Exception\Data\TypeException
     * @throws \Qubus\Exception\Exception
     * @throws \ReflectionException
     */
    public function revisions(ContentRevisionService $revisions, string $contentId): ResponseInterface
    {
        return new JsonResponse([
            'success' => true,
            'can_restore' => current_user_can(perm: 'restore:content_revisions'),
            'revisions' => $revisions->revisions($contentId),
        ]);
    }

    /**
     * @param ServerRequest $request
     * @param ContentRevisionService $revisions
     * @param string $contentId
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function restoreRevision(
        ServerRequest $request,
        ContentRevisionService $revisions,
        string $contentId
    ): ResponseInterface {
        $body = $request->getParsedBody();

        $revisions->restore(
            contentId: $contentId,
            eventId: (string) ($body['event_id'] ?? '')
        );

        return new JsonResponse([
            'success' => true,
            'message' => trans_html('Revision restored as draft.'),
        ]);
    }

    /**
     * @param ServerRequest $request
     * @param ContentRevisionDiffService $diff
     * @param string $contentId
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function revisionDiff(
        ServerRequest $request,
        ContentRevisionDiffService $diff,
        string $contentId
    ): ResponseInterface {
        return new JsonResponse([
            'success' => true,
            'changes' => $diff->diff(
                contentId: $contentId,
                eventId: (string) ($request->getQueryParams()['event_id'] ?? '')
            ),
        ]);
    }

    /**
     * @param ServerRequest $request
     * @param ContentWorkflowService $workflow
     * @param string $contentId
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws Exception
     */
    public function comments(
        ServerRequest $request,
        ContentWorkflowService $workflow,
        string $contentId
    ): ResponseInterface {
        return new JsonResponse([
            'success' => true,
            'permissions' => $this->permissions(),
            'comments' => $workflow->comments(
                contentId: $contentId,
                status: (string) ($request->getQueryParams()['status'] ?? 'open')
            ),
        ]);
    }
}
