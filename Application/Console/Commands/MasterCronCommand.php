<?php

declare(strict_types=1);

namespace App\Application\Console\Commands;

use App\Infrastructure\Http\Controllers\CronController;
use Codefy\Framework\Console\ConsoleCommand;
use Qubus\Http\ServerRequest;

final class MasterCronCommand extends ConsoleCommand
{
    protected string $name = 'cms:cron';

    protected function configure(): void
    {
        $this->setDescription('Publishes scheduled products and runs each site cron hook.');
    }

    protected function handle(): int
    {
        $this->codefy->make(CronController::class)->master(new ServerRequest());

        return self::SUCCESS;
    }
}
