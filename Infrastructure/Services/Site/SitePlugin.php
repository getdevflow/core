<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Site;

use App\Infrastructure\Services\Extension;
use App\Infrastructure\Services\Options;
use Qubus\Expressive\Database;

abstract class SitePlugin implements Extension
{
    public function __construct(
        protected Options $option,
        protected Database $dfdb,
    ) {
    }

    protected function id(): string
    {
        return $this->meta()['id'];
    }

    protected function name(): string
    {
        return $this->meta()['name'];
    }
}
