<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Content\Workflow;

use App\Application\Devflow;
use App\Domain\Content\Command\RestoreRevisionCommand;
use App\Domain\Content\Model\Content;
use App\Domain\Content\ValueObject\ContentId;
use App\Infrastructure\Persistence\Cache\ContentCachePsr16;
use Codefy\CommandBus\Exceptions\CommandPropertyNotFoundException;
use Codefy\CommandBus\Exceptions\UnresolvableCommandHandlerException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Expressive\Database;
use Qubus\Support\DateTime\QubusDateTimeImmutable;
use Qubus\ValueObjects\Identity\Ulid;
use Qubus\ValueObjects\StringLiteral\StringLiteral;
use ReflectionException;
use RuntimeException;

use function App\Shared\Helpers\current_user_can;
use function App\Shared\Helpers\get_content_by_id;
use function App\Shared\Helpers\get_current_user_id;
use function array_filter;
use function array_key_exists;
use function array_map;
use function Codefy\Framework\Helpers\command;
use function Codefy\Framework\Helpers\logger;
use function implode;
use function is_array;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final readonly class ContentRevisionService
{
    private const array REVISION_EVENT_TYPES = [
        'content-was-created',
        'content-title-was-changed',
        'content-slug-was-changed',
        'content-body-was-changed',
    ];

    public function __construct(private Database $dfdb)
    {
    }

    /**
     * @param string $contentId
     * @param int $limit
     * @return array
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function revisions(string $contentId, int $limit = 25): array
    {
        if (false === current_user_can(perm: 'view:content_revisions')) {
            throw new RuntimeException('Access denied.');
        }

        $rows = $this->dfdb
            ->table($this->dfdb->prefix . 'event_store')
            ->where('aggregate_id', $contentId)->and()
            ->whereIn(
                'event_type',
                self::REVISION_EVENT_TYPES
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
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Exception
     */
    public function restore(string $contentId, string $eventId): void
    {
        if (false === current_user_can(perm: 'restore:content_revisions')) {
            throw new \RuntimeException('Access denied.');
        }

        $data = $this->snapshotForRevision($contentId, $eventId);

        try {
            command(
                new RestoreRevisionCommand([
                    'id' => ContentId::fromString($contentId),
                    'title' => StringLiteral::fromNative($data['content_title']),
                    'slug' => StringLiteral::fromNative($data['content_slug']),
                    'body' => StringLiteral::fromNative($data['content_body']),
                    'modified' => QubusDateTimeImmutable::now(),
                    'modifiedGmt' => QubusDateTimeImmutable::now('UTC'),
                ])
            );

            $this->dfdb->transactional(function () use ($contentId, $eventId, $data) {
                $this->dfdb->table($this->dfdb->prefix . 'content_workflow_activity')->insert([
                    'activity_id' => Ulid::generateAsString(),
                    'content_id' => $contentId,
                    'user_id' => get_current_user_id(),
                    'activity_type' => 'revision_restored',
                    'from_status' => null,
                    'to_status' => 'draft',
                    'message' => 'Revision restored as draft.',
                    'metadata' => json_encode([
                        'event_id' => $eventId,
                        'restored_title' => $data['content_title'],
                    ], JSON_THROW_ON_ERROR),
                    'created_at' => QubusDateTimeImmutable::now('UTC')->toDateTimeString(),
                ]);
            });

            /** @var Content $content */
            $content = get_content_by_id($contentId);
            ContentCachePsr16::clean($content);

            Devflow::$PHP->flash->success(Devflow::$PHP->flash->notice(200));
        } catch (UnresolvableCommandHandlerException | ReflectionException | CommandPropertyNotFoundException $e) {
            logger('error', $e->getMessage());
            throw new RuntimeException('Revision restore failed.', previous: $e);
        }
    }

    private function normalizeRevision(array $row): array
    {
        $payload = json_decode((string) $row['payload'], true) ?: [];
        $metadata = json_decode((string) $row['metadata'], true) ?: [];

        $revisionPayload = $this->normalizeRevisionPayload($payload);

        return [
            'event_id' => $row['event_id'],
            'event_type' => $row['event_type'],
            'playhead' => $row['aggregate_playhead'],
            'recorded_at' => $row['recorded_at'],
            'user_id' => $metadata['user_id'] ?? $metadata['userId'] ?? null,
            'title' => $revisionPayload['content_title'] ?? null,
            'slug' => $revisionPayload['content_slug'] ?? null,
            'body' => $revisionPayload['content_body'] ?? null,
            'payload' => $revisionPayload,
            'metadata' => $metadata,
        ];
    }

    private function snapshotForRevision(string $contentId, string $eventId): array
    {
        $target = $this->dfdb
            ->table($this->dfdb->prefix . 'event_store')
            ->where('event_id', $eventId)->and()
            ->where('aggregate_id', $contentId)
            ->findOne();

        if ($target === false) {
            throw new RuntimeException('Revision not found.');
        }

        $events = $this->dfdb
            ->table($this->dfdb->prefix . 'event_store')
            ->where('aggregate_id', $contentId)->and()
            ->whereIn('event_type', self::REVISION_EVENT_TYPES)->and()
            ->where('aggregate_playhead <= ?', $target->aggregate_playhead)
            ->orderBy('aggregate_playhead', 'DESC')
            ->find(callback: static fn(array $rows): array => $rows);

        $snapshot = [
            'content_title' => null,
            'content_slug' => null,
            'content_body' => null,
        ];

        foreach ($events as $event) {
            $payload = json_decode((string) $event['payload'], true);

            if (! is_array($payload)) {
                continue;
            }

            $normalized = $this->normalizeRevisionPayload($payload);

            foreach ($snapshot as $field => $value) {
                if ($value === null && array_key_exists($field, $normalized)) {
                    $snapshot[$field] = $normalized[$field];
                }
            }

            if (
                    $snapshot['content_title'] !== null &&
                    $snapshot['content_slug'] !== null &&
                    $snapshot['content_body'] !== null
            ) {
                break;
            }
        }

        return $this->assertCompleteSnapshot($snapshot);
    }

    private function normalizeRevisionPayload(array $payload): array
    {
        $data = $payload['content'] ?? $payload;

        return array_filter([
            'content_title' => $data['title'] ?? $data['content_title'] ?? null,
            'content_slug' => $data['slug'] ?? $data['content_slug'] ?? null,
            'content_body' => $data['body'] ?? $data['content_body'] ?? null,
        ], static fn($value): bool => $value !== null);
    }

    private function assertCompleteSnapshot(array $snapshot): array
    {
        $missing = [];

        foreach (['content_title', 'content_slug', 'content_body'] as $field) {
            if ($snapshot[$field] === null) {
                $missing[] = $field;
            }
        }

        if ($missing !== []) {
            throw new RuntimeException(
                'Cannot restore this revision because the event history does not contain a complete snapshot. Missing: ' .
                implode(', ', $missing)
            );
        }

        return $snapshot;
    }
}
