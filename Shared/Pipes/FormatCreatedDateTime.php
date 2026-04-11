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
use function str_replace;

class FormatCreatedDateTime
{
    /**
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function handle(ServerRequestInterface $request, Closure $next): mixed
    {
        $body = $request->getParsedBody();

        $created = new DateTime(time: 'now', timezone: get_user_timezone())->format();
        $createdGmt = new DateTime($created)->gmtdate();

        $dateTime = array_merge($body, [
            'created' => $created,
            'createdGmt' => $createdGmt,

        ]);
        $request = $request->withParsedBody($dateTime);

        return $next($request);
    }
}
