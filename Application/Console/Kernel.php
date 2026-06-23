<?php

declare(strict_types=1);

namespace App\Application\Console;

use Codefy\Framework\Console\ConsoleKernel;
use Codefy\Framework\Scheduler\Schedule;
use Qubus\EventDispatcher\ActionFilter\Action;
use Qubus\Exception\Exception;
use ReflectionException;
use Symfony\Component\Console\Command\SignalableCommandInterface;

final class Kernel extends ConsoleKernel
{
    /**
     * Add your custom console commands here.
     *
     * @var array<class-string<SignalableCommandInterface>|callable>
     */
    protected array $commands = [
        \App\Application\Console\Commands\PluginInstallCommand::class,
        \App\Application\Console\Commands\PluginRemoveCommand::class,
        \App\Application\Console\Commands\ThemeInstallCommand::class,
        \App\Application\Console\Commands\ThemeRemoveCommand::class,
        \App\Application\Console\Commands\SiteMigrationCommand::class,
        \App\Application\Console\Commands\SecurityAuditCommand::class,
        \App\Application\Console\Commands\ExtensionCacheWarmCommand::class,
        \App\Application\Console\Commands\PublishScheduledContentCommand::class,
    ];

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
        $schedule->command(command: 'extension:cache:warm')->hourly();
        $schedule->command(command: 'content:publish-scheduled')->everyMinute();
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
        $this->load();
    }
}
