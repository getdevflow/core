<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middlewares;

use App\Domain\Site\Model\Site;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Qubus\Exception\Exception;
use Qubus\Expressive\Database;

final readonly class CurrentSiteMiddleware implements MiddlewareInterface
{
    public function __construct(private Database $dfdb)
    {
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $host = strtolower($request->getUri()->getHost());

        $results = $this->dfdb->getResults(
            $this->dfdb->prepare(
                "SELECT * FROM {$this->dfdb->basePrefix}site WHERE site_domain = ? OR site_mapping = ?",
                [
                    $host,
                    $host
                ]
            ),
            Database::ARRAY_A
        );

        if ($results !== false) {
            $site = new Site($this->dfdb);
            $site->create($results);

            $request = $request->withAttribute('site', $site);
        } else {
            $request = $request->withAttribute('site', false);
        }

        return $handler->handle($request);
    }
}
