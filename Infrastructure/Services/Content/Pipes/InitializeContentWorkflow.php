<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Content\Pipes;

use Psr\Http\Message\ServerRequestInterface;

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

        return $next($request->withParsedBody($body));
    }
}
