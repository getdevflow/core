<?php

declare(strict_types=1);

namespace App\Application\Console;

use Codefy\Framework\Console\ConsoleKernel;
use Codefy\Framework\Scheduler\Schedule;
use Qubus\EventDispatcher\ActionFilter\Action;
use Qubus\Exception\Exception;
use ReflectionException;

class Kernel extends ConsoleKernel
{
    /**
     * Place all your scheduled tasks here.
     *
     * @param Schedule $schedule
     * @return void
     * @throws Exception
     * @throws ReflectionException
     */
    protected function schedule(Schedule $schedule): void
    {
        Action::getInstance()->doAction('scheduler', $schedule);
    }

    /**
     * Place all your commands here that need to be registered
     * to your application.
     *
     * @return void
     */
    protected function commands(): void
    {
        $commands = $this->codefy->make('codefy.config')->getConfigKey('app.commands');

        foreach ($commands as $command) {
            $command = $this->codefy->make($command);
            $this->registerCommand($command);
        }
    }
}
