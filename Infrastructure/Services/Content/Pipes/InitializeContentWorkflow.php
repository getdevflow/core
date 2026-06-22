<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Content\Pipes;

use Psr\Http\Message\ServerRequestInterface;

use function array_merge;

final class InitializeContentWorkflow
{
    public function __invoke(ServerRequestInterface $request, callable $next): ServerRequestInterface
    {
        $body = $request->getParsedBody();

        $body['content_field']['workflow'] ??= [
            'stage' => match ($body['status'] ?? 'draft') {
                'published' => 'published',
                'scheduled' => 'scheduled',
                'pending' => 'in_review',
                'archived' => 'archived',
                default => 'draft',
            },
            'approval_required' => false,
            'reviewers' => [],
        ];

        $body = array_merge($body, ['content_field' => $body['content_field']]);

        return $next($request->withParsedBody($body));
    }
}
