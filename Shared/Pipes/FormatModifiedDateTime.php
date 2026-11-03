<?php

declare(strict_types=1);

namespace App\Shared\Pipes;

use App\Shared\Services\DateTime;
use Closure;
use DateInvalidTimeZoneException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Exception;
use ReflectionException;

use function App\Shared\Helpers\get_user_timezone;
use function str_replace;

class FormatModifiedDateTime
{
    /**
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws DateInvalidTimeZoneException
     */
    public function handle(ServerRequestInterface $request, Closure $next): mixed
    {
        $body = $request->getParsedBody();
        $transformPublished = str_replace(
            search: [' AM', ' PM'],
            replace: '',
            subject: $body['published']
        );
        $publishedDate = new DateTime($transformPublished, get_user_timezone());
        $published = $publishedDate->format('Y-m-d H:i:sP');
        $publishedGmt = $publishedDate->gmtdate();

        $modifiedDate = new DateTime('now', get_user_timezone());
        $modified = $modifiedDate->format('Y-m-d H:i:sP');
        $modifiedGmt = $modifiedDate->gmtdate();

        // Carry the local offset through validation and DTO parsing.
        $dateTime = array_merge($body, [
            'published' => $published,
            'publishedGmt' => $publishedGmt,
            'modified' => $modified,
            'modifiedGmt' => $modifiedGmt,

        ]);
        $request = $request->withParsedBody($dateTime);

        return $next($request);
    }
}
