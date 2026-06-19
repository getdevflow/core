<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Infrastructure\Services\Content\Workflow\ContentNotificationService;
use Codefy\Framework\Http\BaseController;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Qubus\Http\ServerRequest;

use function App\Shared\Helpers\get_current_user_id;

final class AdminContentNotificationController extends BaseController
{
    public function unread(ContentNotificationService $notifications): ResponseInterface
    {
        return new JsonResponse([
            'success' => true,
            'notifications' => $notifications->unread(get_current_user_id()),
        ]);
    }

    public function markRead(
        ServerRequest $request,
        ContentNotificationService $notifications
    ): ResponseInterface {
        $notifications->markRead(
            notificationId: (string) ($request->getParsedBody()['notification_id'] ?? ''),
            userId: get_current_user_id()
        );

        return new JsonResponse(['success' => true]);
    }

    public function markAllRead(ContentNotificationService $notifications): ResponseInterface
    {
        $notifications->markAllRead(get_current_user_id());

        return new JsonResponse(['success' => true]);
    }
}
