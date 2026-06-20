<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Infrastructure\Services\Content\Workflow\ContentRevisionDiffService;
use App\Infrastructure\Services\Content\Workflow\ContentRevisionService;
use App\Infrastructure\Services\Content\Workflow\ContentWorkflowService;
use Codefy\Framework\Http\BaseController;
use Exception;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Qubus\Http\ServerRequest;

use function App\Shared\Helpers\current_user_can;
use function App\Shared\Helpers\get_current_user_id;

final class AdminContentWorkflowController extends BaseController
{
    /**
     * @param ServerRequest $request
     * @param ContentWorkflowService $workflow
     * @param string $contentId
     * @return ResponseInterface
     * @throws \Qubus\Exception\Exception
     * @throws \ReflectionException
     * @throws Exception
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
     * @throws \Qubus\Exception\Exception
     * @throws \ReflectionException
     * @throws Exception
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
     * @throws \Qubus\Exception\Exception
     * @throws \ReflectionException
     * @throws Exception
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
     * @throws \Qubus\Exception\Exception
     * @throws \ReflectionException
     * @throws Exception
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
     * @throws \JsonException
     * @throws \Qubus\Exception\Exception
     * @throws \ReflectionException
     */
    public function comment(
        ServerRequest $request,
        ContentWorkflowService $workflow,
        string $contentId
    ): ResponseInterface {
        $body = $request->getParsedBody();

        return new JsonResponse([
            'success' => true,
            'comment' => $workflow->comment(
                contentId: $contentId,
                userId: get_current_user_id(),
                body: (string) ($body['comment'] ?? ''),
                parentId: $body['parent_id'] !== '' ? ($body['parent_id'] ?? null) : null,
                selection: isset($body['selection']) && is_array($body['selection']) ? $body['selection'] : null,
                type: (string) ($body['comment_type'] ?? 'editorial')
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
     * @throws \JsonException
     * @throws \Qubus\Exception\Exception
     * @throws \ReflectionException
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
     * @throws \JsonException
     * @throws \Qubus\Exception\Exception
     * @throws \ReflectionException
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
     * @throws \JsonException
     * @throws \Qubus\Exception\Exception
     * @throws \ReflectionException
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
     * @throws \JsonException
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
            'message' => 'Revision restored as draft.',
        ]);
    }

    /**
     * @param ServerRequest $request
     * @param ContentRevisionDiffService $diff
     * @param string $contentId
     * @return ResponseInterface
     * @throws \JsonException
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

    public function comments(ContentWorkflowService $workflow, string $contentId): ResponseInterface
    {
        return new JsonResponse([
            'success' => true,
            'permissions' => $this->permissions(),
            'comments' => $workflow->comments($contentId),
        ]);
    }
}
