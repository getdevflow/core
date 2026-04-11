<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use Codefy\Framework\DataCollector\CodefyCollector;
use Codefy\Framework\DataCollector\RouteCollector;
use Codefy\Framework\Http\RequestContext;
use Codefy\Framework\Support\CodefyServiceProvider;
use DebugBar\DataCollector\PDO\PDOCollector;
use DebugBar\DataCollector\PDO\TraceablePDO;
use DebugBar\DebugBar;
use DebugBar\StandardDebugBar;
use Qubus\Routing\Route\Route;
use Qubus\Routing\Route\RouteAttributes;

final class DebugBarServiceProvider extends CodefyServiceProvider
{
    public function register(): void
    {
        if($this->codefy->isRunningInConsole()) {
            return;
        }

        $this->codefy->singleton(DebugBar::class, function () {
            $request = RequestContext::get();

            /** @var Route $route */
            $route = $request->getAttribute(RouteAttributes::ROUTE);
            /** @var string $routeName */
            $routeName = $request->getAttribute(RouteAttributes::NAME);
            $pdo = new TraceablePDO($this->codefy->getDbConnection()->pdo);

            $debugbar = new StandardDebugBar();
            $debugbar->addCollector(new PdoCollector($pdo));
            $debugbar->addCollector(new RouteCollector($routeName ?: 'No Name', $route->getActionName()));
            $debugbar->addCollector(new CodefyCollector());

            return $debugbar;
        });
    }
}
