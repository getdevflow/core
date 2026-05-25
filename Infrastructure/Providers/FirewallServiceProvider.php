<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use Codefy\Framework\Security\Firewall\NullThreatLogger;
use Codefy\Framework\Security\Firewall\ThreatLogger;
use Codefy\Framework\Support\CodefyServiceProvider;

class FirewallServiceProvider extends CodefyServiceProvider
{
    public function register(): void
    {
        $this->codefy->alias(ThreatLogger::class, NullThreatLogger::class);
    }
}
