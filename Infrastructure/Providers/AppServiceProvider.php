<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Infrastructure\Services\Content\Workflow\Queue\ContentWorkflowEmailNotification;
use App\Infrastructure\Services\Queue\EmailChangeNotification;
use App\Infrastructure\Services\Queue\NewAccountNotification;
use App\Infrastructure\Services\Queue\ResetPasswordNotification;
use Codefy\Framework\Scheduler\Mutex\FileLocker;
use Codefy\Framework\Scheduler\Mutex\Locker;
use Codefy\Framework\Support\CodefyServiceProvider;
use Qubus\Exception\Data\TypeException;

final class AppServiceProvider extends CodefyServiceProvider
{
    /**
     * @throws TypeException
     */
    public function register(): void
    {
        $config = $this->codefy->configContainer;
        $config->setConfigKey('queue', ['jobs' => array_values(array_unique(array_merge(
            $config->array('queue.jobs', []),
            [NewAccountNotification::class, EmailChangeNotification::class,
                ResetPasswordNotification::class, ContentWorkflowEmailNotification::class]
        )))]);

        $this->codefy->singleton(Locker::class, fn () => new FileLocker(
            $this->codefy->storagePath() . '/scheduler-locks'
        ));
    }
}
