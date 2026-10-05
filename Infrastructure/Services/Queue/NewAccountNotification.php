<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Queue;

use App\Application\Devflow;
use App\Infrastructure\Services\Queue\NotificationJob;
use Exception;
use Psr\SimpleCache\InvalidArgumentException;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

use function Codefy\Framework\Helpers\env;
use function Codefy\Framework\Helpers\logger;
use function Codefy\Framework\Helpers\resource_path;
use function Codefy\Framework\Helpers\trans;
use function Qubus\Security\Helpers\__observer;
use function sprintf;

final class NewAccountNotification extends NotificationJob
{
    public string $name = 'New User Account';

    /**
     * @param array{login:string,sitename:string,email:string,url:string} $data
     */
    protected const array FIELDS = ['login', 'sitename', 'email', 'url'];

    /**
     * @inheritDoc
     * @return bool
     * @throws TransportExceptionInterface
     */
    public function handle(): bool
    {
        try {
            $mailer = Devflow::$PHP->mailer;

            $message = '<p>' . sprintf(
                trans('<strong>Username:</strong> %s'),
                htmlspecialchars($this->data['login'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            ) . '</p>';
            $sender = __observer()->filter->applyFilter('system.sender.email', env(key: 'MAILER_FROM_EMAIL'));

            $mailer
                ->withTransport()
                ->withFrom(
                    address: $sender,
                    name: $this->data['sitename']
                )
                ->withTo(address: $this->data['email'])
                ->withSubject(
                    subject: sprintf(
                        trans('[%s] New Account'),
                        $this->data['sitename']
                    ),
                )
                ->withBody(
                    data: [
                        'site_name' => $this->data['sitename'],
                        'notification_type' => trans('Account'),
                        'notification_title' => trans('New Account'),
                        'user' => htmlspecialchars($this->data['login'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                        'action_url' => $this->data['url'],
                        'action_label' => trans('Sign in'),
                        'notification_message' => $message,
                    ],
                    options: ['template_name' => resource_path(path: 'tpl/notification-email.html')]
                )
                ->withCustomHeader(
                    name: 'X-Mailer',
                    value: sprintf('Devflow %s', Devflow::release())
                )
                ->withHtml(isHtml: true)
                ->send();

            return true;
        } catch (
            InvalidArgumentException |
            \Qubus\Exception\Exception |
            Exception $e
        ) {
            logger(level: 'error', message: $e->getMessage());
        }

        return false;
    }
}
