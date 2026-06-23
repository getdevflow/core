<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Content\Workflow;

use Qubus\Expressive\Database;

use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\get_content_by_id;
use function gmdate;
use function Qubus\Security\Helpers\esc_html;
use function sprintf;

final readonly class ContentNotificationService
{
    public function __construct(private Database $dfdb)
    {
    }

    /**
     * @param string $userId
     * @param int $limit
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Qubus\Exception\Exception
     * @throws \ReflectionException
     */
    public function unread(string $userId, int $limit = 15): array
    {
        $rows = $this->dfdb
            ->table($this->dfdb->prefix . 'content_notification')
            ->where('user_id', $userId)
            ->where('is_read', 0)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->find(callback: static fn(array $rows): array => $rows);

        return array_map(static function (array $row): array {
            $content = get_content_by_id(esc_html($row['content_id']));
            $baseUrl = admin_url(path: sprintf('content-type/%s/%s/', $content->type, $content->id));

            $url = match (esc_html($row['notification_type'])) {
                'comment_added',
                'comment_replied',
                'comment_resolved'
                => $baseUrl . '#editorial-comments-box',

                'revision_restored'
                => $baseUrl . '#content-revisions-box',

                default
                => $baseUrl . '#content-workflow-box',
            };

            $row['url'] = ! empty($content->id)
                ? $url
                : admin_url();

            return $row;
        }, $rows);
    }

    /**
     * @param string $notificationId
     * @param string $userId
     * @return void
     * @throws \Exception
     */
    public function markRead(string $notificationId, string $userId): void
    {
        $this->dfdb->transactional(function () use ($notificationId, $userId): void {
            $this->dfdb
                ->table($this->dfdb->prefix . 'content_notification')
                ->where('notification_id', $notificationId)
                ->where('user_id', $userId)
                ->update([
                    'is_read' => 1,
                    'read_at' => gmdate('Y-m-d H:i:s'),
                ]);
        });
    }

    /**
     * @param string $userId
     * @return void
     * @throws \Exception
     */
    public function markAllRead(string $userId): void
    {
        $this->dfdb->transactional(function () use ($userId): void {
            $this->dfdb
                ->table($this->dfdb->prefix . 'content_notification')
                ->where('user_id', $userId)
                ->where('is_read', 0)
                ->update([
                    'is_read' => 1,
                    'read_at' => gmdate('Y-m-d H:i:s'),
                ]);
        });
    }
}
