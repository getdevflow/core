<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Content\Pipes;

use Psr\Http\Message\ServerRequestInterface;

use function App\Shared\Helpers\content_workflow_stage_from_status;
use function array_merge;
use function in_array;

final class SyncContentWorkflowStage
{
    public function __invoke(ServerRequestInterface $request, callable $next): ServerRequestInterface
    {
        $body = $request->getParsedBody();

        $status = (string) ($body['status'] ?? 'draft');

        $contentField = (array) ($body['content_field'] ?? []);
        $workflow = (array) ($contentField['workflow'] ?? []);

        $workflow['stage'] = content_workflow_stage_from_status(
            status: $status,
            currentStage: isset($workflow['stage']) ? (string) $workflow['stage'] : null
        );

        if ($status !== 'pending' || $workflow['stage'] !== 'approved') {
            unset(
                $workflow['approved_by'],
                $workflow['approved_at']
            );
        }

        if (! in_array($workflow['stage'], ['in_review', 'approved'], true)) {
            $workflow['review_ready'] = false;
        }

        $contentField['workflow'] = $workflow;

        $body = array_merge($body, [
            'content_field' => $contentField,
        ]);

        return $next($request->withParsedBody($body));
    }
}
