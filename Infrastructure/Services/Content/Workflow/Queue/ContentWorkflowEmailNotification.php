<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Content\Workflow\Queue;

use App\Application\Devflow;
use App\Infrastructure\Services\Queue\NotificationJob;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

use function Codefy\Framework\Helpers\env;
use function Codefy\Framework\Helpers\logger;
use function Codefy\Framework\Helpers\resource_path;
use function Codefy\Framework\Helpers\trans;
use function Qubus\Security\Helpers\__observer;

final class ContentWorkflowEmailNotification extends NotificationJob
{
    public string $name = 'Content Workflow Notification';

    public string $schedule = '* * * * *';

    public int $executions = 5;

    /**
     * @param array{
     *     email:string,
     *     user:string,
     *     sitename:string,
     *     notification_type:string,
     *     notification_title:string,
     *     notification_message:string,
     *     action_url:string,
     *     action_label:string
     * } $data
     */
    protected const array FIELDS = [
        'email', 'user', 'sitename', 'notification_type', 'notification_title',
        'notification_message', 'action_url', 'action_label',
    ];

    /**
     * @return bool
     * @throws \Qubus\Exception\Exception
     */
    public function handle(): bool
    {
        $mailer = Devflow::$PHP->mailer;
        $sender = __observer()->filter->applyFilter('system.sender.email', env(key: 'MAILER_FROM_EMAIL'));

        try {

            $mailer
                ->withTransport()
                ->withFrom(address: $sender, name: $this->data['sitename'])
                ->withTo(address: $this->data['email'])
                ->withSubject(
                    subject: sprintf(
                        trans('[%s] %s'),
                        $this->data['sitename'],
                        $this->data['notification_title']
                    ),
                )
                ->withBody(
                    data: [
                        'site_name' => $this->data['sitename'],
                        'notification_type' => $this->data['notification_type'],
                        'notification_title' => $this->data['notification_title'],
                        'user' => $this->data['user'],
                        'action_url' => $this->data['action_url'],
                        'action_label' => $this->data['action_label'],
                        'notification_message' => $this->data['notification_message'],
                    ],
                    options: [
                        'template_name' => resource_path(path: 'tpl/notification-email.html'),
                    ]
                )
                ->withCustomHeader('X-Mailer', sprintf('Devflow %s', Devflow::release()))
                ->withHtml(isHtml: true)
                ->send();

            return true;
        } catch (TransportExceptionInterface $e) {
            logger(level: 'error', message: $e->getMessage());
        }

        return false;
    }
}
