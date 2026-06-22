<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Content\Workflow;

use App\Domain\Content\Command\RestoreRevisionCommand;
use App\Domain\Content\ValueObject\ContentId;
use App\Shared\ValueObject\ArrayLiteral;
use Codefy\CommandBus\Exceptions\CommandPropertyNotFoundException;
use Codefy\CommandBus\Exceptions\UnresolvableCommandHandlerException;
use JsonException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Expressive\Database;
use Qubus\Support\DateTime\QubusDateTimeImmutable;
use Qubus\ValueObjects\StringLiteral\StringLiteral;
use ReflectionException;

use function App\Shared\Helpers\current_user_can;
use function Codefy\Framework\Helpers\command;
use function json_decode;

final readonly class ContentRevisionService
{
    public function __construct(private Database $dfdb)
    {
    }

    public function revisions(string $contentId, int $limit = 25): array
    {
        $rows = $this->dfdb
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

        return array_map(fn(array $row): array => $this->normalizeRevision($row), $rows);
    }

    /**
     * @param string $contentId
     * @param string $eventId
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws CommandPropertyNotFoundException
     * @throws UnresolvableCommandHandlerException
     */
    public function restore(string $contentId, string $eventId): void
    {
        if (false === current_user_can(perm: 'restore:content_revisions')) {
            throw new \RuntimeException('Access denied.');
        }

        $event = $this->dfdb
            ->table($this->dfdb->prefix . 'event_store')
            ->where('event_id', $eventId)
            ->where('aggregate_id', $contentId)
            ->findOne();

        if ($event === false) {
            throw new \RuntimeException('Revision not found.');
        }

        $payload = json_decode((string) $event->payload, true, 512, JSON_THROW_ON_ERROR);

        $data = $payload['content'] ?? $payload;

        command(
            new RestoreRevisionCommand([
                'id' => ContentId::fromString($contentId),
                'title' => StringLiteral::fromNative($data['content_title']),
                'slug' => StringLiteral::fromNative($data['content_slug']),
                'body' => StringLiteral::fromNative($data['content_body']),
                'attribute' => ArrayLiteral::fromNative(json_decode($data['content_attribute'], true)),
                'status' => StringLiteral::fromNative('draft'),
                'modified' => QubusDateTimeImmutable::now(),
                'modifiedGmt' => QubusDateTimeImmutable::now('UTC'),
            ])
        );
    }

    private function normalizeRevision(array $row): array
    {
        $payload = json_decode((string) $row['payload'], true) ?: [];
        $metadata = json_decode((string) $row['metadata'], true) ?: [];

        return [
            'event_id' => $row['event_id'],
            'event_type' => $row['event_type'],
            'playhead' => $row['aggregate_playhead'],
            'recorded_at' => $row['recorded_at'],
            'user_id' => $metadata['user_id'] ?? $metadata['userId'] ?? null,
            'title' => $payload['title'] ?? $payload['content_title'] ?? $payload['content']['title'] ?? null,
            'status' => $payload['status'] ?? $payload['content_status'] ?? $payload['content']['status'] ?? null,
            'payload' => $payload,
            'metadata' => $metadata,
        ];
    }
}
