<?php

declare(strict_types=1);

namespace App\Shared\Pipes;

use App\Shared\Services\DateTime;
use Closure;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Exception;
use ReflectionException;

use function App\Shared\Helpers\get_user_timezone;

class CheckForScheduledStatus
{
    /**
     * @param ServerRequestInterface $request
     * @param Closure $next
     * @return mixed
     * @throws Exception
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     */
    public function handle(ServerRequestInterface $request, Closure $next): mixed
    {
        $body = $request->getParsedBody();

        $published = new DateTime(
            time: $body['published'],
            timezone: get_user_timezone()
        )->getDateTime();

        if (
                $body['status'] !== 'scheduled' &&
                (
                    $published->format('Y-m-d H:i:s') >
                    new DateTime('now', get_user_timezone())->format()
                )
        ) {
            $body['status'] = 'scheduled';
        }

        $status = array_merge($body, [
            'status' => $body['status'],
        ]);

        $request = $request->withParsedBody($status);

        return $next($request);
    }
}
