<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Queue;

use App\Application\Devflow;
use App\Infrastructure\Services\Queue\NotificationJob;
use Exception;
use Psr\SimpleCache\InvalidArgumentException;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

use function App\Shared\Helpers\get_option;
use function Codefy\Framework\Helpers\env;
use function Codefy\Framework\Helpers\logger;
use function Codefy\Framework\Helpers\resource_path;
use function Codefy\Framework\Helpers\trans;
use function Qubus\Security\Helpers\__observer;
use function sprintf;

class EmailChangeNotification extends NotificationJob
{
    public string $name = 'Email Updated';

    /**
     * @param array{login:string,admin:string,sitename:string,email:string,url:string} $data
     */
    protected const array FIELDS = ['login', 'admin', 'sitename', 'email', 'url'];

    /**
     * @inheritDoc
     * @return bool
     * @throws TransportExceptionInterface
     */
    public function handle(): bool
    {
        try {
            $mailer = Devflow::$PHP->mailer;

            $message = "<p>" . sprintf(
                trans(
                    "This is confirmation that your email on %s was updated.",
                ),
                $this->data['sitename']
            );
            $message .= "</p>";
            $message .= "<p>" . sprintf(trans(string: '<strong>Email:</strong> %s'), $this->data['email']) . "</p>";
            $message .= "<p>" . sprintf(
                trans(
                    'If you did not initiate an email change/update, please contact us at <a href="mailto:%s">%s</a>.',
                ),
                htmlspecialchars($this->data['admin'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                htmlspecialchars($this->data['admin'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            ) . "</p>";
            $sender = __observer()->filter->applyFilter('system.sender.email', env(key: 'MAILER_FROM_EMAIL'));

            $mailer
                ->withTransport()
                ->withFrom(
                    address: $sender,
                    name: $this->data['sitename'],
                )
                ->withTo(address: $this->data['email'])
                ->withSubject(subject:
                    sprintf(
                        trans('[%s] Notice of Email Change'),
                        $this->data['sitename']
                    ),)
                ->withBody(
                    data: [
                        'site_name' => $this->data['sitename'],
                        'notification_type' => trans('Profile Update'),
                        'notification_title' => trans('Email Change'),
                        'user' => htmlspecialchars($this->data['login'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                        'action_url' => $this->data['url'],
                        'action_label' => trans('Sign in'),
                        'notification_message' => $message,
                    ],
                    options: ['template_name' => resource_path(path: 'tpl/notification-email.html')]
                )
                ->withCustomHeader('X-Mailer', sprintf('Devflow %s', Devflow::release()))
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
