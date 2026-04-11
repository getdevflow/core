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

class FormatPublishedDateTime
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
        $transformPublished = str_replace(
            search: [' AM', ' PM'],
            replace: '',
            subject: $body['published']
        );
        $published = new DateTime($transformPublished, get_user_timezone())->format();
        $publishedGmt = new DateTime($published)->gmtdate();

        $dateTime = array_merge($body, [
            'published' => $published,
            'publishedGmt' => $publishedGmt,

        ]);
        $request = $request->withParsedBody($dateTime);

        return $next($request);
    }
}
