<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Infrastructure\Services\Content\Workflow\ContentNotificationService;
use Codefy\Framework\Http\BaseController;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Exception;
use Qubus\Http\ServerRequest;
use ReflectionException;

use function App\Shared\Helpers\get_current_user_id;

final class AdminContentNotificationController extends BaseController
{
    /**
     * @param ContentNotificationService $notifications
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ReflectionException
     */
    public function unread(ContentNotificationService $notifications): ResponseInterface
    {
        return new JsonResponse([
            'success' => true,
            'notifications' => $notifications->unread(get_current_user_id()),
        ]);
    }

    /**
     * @param ServerRequest $request
     * @param ContentNotificationService $notifications
     * @return ResponseInterface
     * @throws \Qubus\Exception\Exception
     * @throws \ReflectionException
     * @throws \Exception
     */
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

    /**
     * @param ContentNotificationService $notifications
     * @return ResponseInterface
     * @throws \Qubus\Exception\Exception
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function markAllRead(ContentNotificationService $notifications): ResponseInterface
    {
        $notifications->markAllRead(get_current_user_id());

        return new JsonResponse(['success' => true]);
    }
}
