<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Infrastructure\Services\Content\Workflow\ContentRevisionDiffService;
use App\Infrastructure\Services\Content\Workflow\ContentRevisionService;
use App\Infrastructure\Services\Content\Workflow\ContentWorkflowService;
use Codefy\Framework\Http\BaseController;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Qubus\Http\ServerRequest;

use function App\Shared\Helpers\get_current_user_id;

final class AdminContentWorkflowController extends BaseController
{
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
                parentId: $body['parent_id'] ?? null,
                selection: isset($body['selection']) ? (array) $body['selection'] : null
            ),
        ]);
    }

    public function activity(ContentWorkflowService $workflow, string $contentId): ResponseInterface
    {
        return new JsonResponse([
            'success' => true,
            'activity' => $workflow->activity($contentId),
        ]);
    }

    public function revisions(ContentRevisionService $revisions, string $contentId): ResponseInterface
    {
        return new JsonResponse([
            'success' => true,
            'revisions' => $revisions->revisions($contentId),
        ]);
    }

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
            'comments' => $workflow->comments($contentId),
        ]);
    }
}
