<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Queue;

use App\Application\Devflow;
use Codefy\Framework\Queue\SimpleQueue;
use Exception;
use Psr\SimpleCache\InvalidArgumentException;

use function App\Shared\Helpers\get_option;
use function Codefy\Framework\Helpers\env;
use function Codefy\Framework\Helpers\logger;
use function Codefy\Framework\Helpers\resource_path;
use function Codefy\Framework\Helpers\trans;
use function Qubus\Security\Helpers\__observer;
use function sprintf;

class EmailChangeNotification extends SimpleQueue
{
    public string $name = 'Email Updated' {
        get => $this->name;
        set(string $value) => $this->name = $value;
    }

    /**
     * @param array{login:string,admin:string,sitename:string,email:string,url:string} $data
     */
    public function __construct(protected array $data)
    {
    }

    /**
     * @inheritDoc
     * @return bool
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
                $this->data['admin'],
                $this->data['admin']
            ) . "</p>";
            $sender = __observer()->filter->applyFilter('system.sender.email', env(key: 'MAILER_USERNAME'));

            $mailer
                ->withSmtp()
                ->withFrom(
                    address: $sender,
                    name: $this->data['sitename'],
                )
                ->withTo(address: $this->data['email'])
                ->withSubject(subject:
                    sprintf(
                        trans('[%s] Notice of Email Change'),
                        $this->data['sitename']
                    ),
                )
                ->withBody(
                    data: [
                        'site_name' => $this->data['sitename'],
                        'notification_type' => trans('Profile Update'),
                        'notification_title' => trans('Email Change'),
                        'user' => $this->data['login'],
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
            \Qubus\Exception\Exception|
            Exception $e
        ) {
            logger(level: 'error', message: $e->getMessage());
        }

        return false;
    }
}
