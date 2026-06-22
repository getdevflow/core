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
use function array_merge;

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
        $status = (string) ($body['status'] ?? 'draft');
        $publishedInput = (string) ($body['published'] ?? $body['publishedGmt'] ?? '');

        if ($publishedInput === '') {
            return $next($request);
        }

        $published = new DateTime(
            time: $publishedInput,
            timezone: get_user_timezone()
        )->getDateTime();

        $now = new DateTime(
            time: 'now',
            timezone: get_user_timezone()
        )->getDateTime();

        $isFuture = $published > $now;

        if ($status === 'published' && $isFuture) {
            $body['status'] = 'scheduled';
        }

        if ($status === 'scheduled' && !$isFuture) {
            $body['status'] = 'published';
        }

        $status = array_merge($body, [
            'status' => $body['status'],
        ]);

        return $next($request->withParsedBody($status));
    }
}
