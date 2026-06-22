<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Content\Workflow;

use Qubus\Expressive\Database;

use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\get_content_by_id;

final readonly class ContentNotificationService
{
    public function __construct(private Database $dfdb)
    {
    }

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
            $row['url'] = admin_url(
                path: 'content-type/' . get_content_by_id($row['content_id'])->type . '/' . $row['content_id'] . '/#content-activity-box'
            );

            return $row;
        }, $rows);
    }

    public function markRead(string $notificationId, string $userId): void
    {
        $this->dfdb
            ->table($this->dfdb->prefix . 'content_notification')
            ->where('notification_id', $notificationId)
            ->where('user_id', $userId)
            ->update([
                'is_read' => 1,
                'read_at' => gmdate('Y-m-d H:i:s'),
            ]);
    }

    public function markAllRead(string $userId): void
    {
        $this->dfdb
            ->table($this->dfdb->prefix . 'content_notification')
            ->where('user_id', $userId)
            ->where('is_read', 0)
            ->update([
                'is_read' => 1,
                'read_at' => gmdate('Y-m-d H:i:s'),
            ]);
    }
}
