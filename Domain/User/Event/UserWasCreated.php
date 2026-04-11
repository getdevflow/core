<?php

declare(strict_types=1);

namespace App\Domain\User\Event;

use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\Username;
use App\Domain\User\ValueObject\UserToken;
use App\Shared\Services\DateTime;
use App\Shared\ValueObject\ArrayLiteral;
use Codefy\Domain\Aggregate\AggregateId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Codefy\Framework\Support\Password;
use DateTimeInterface;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Support\DateTime\QubusDateTimeImmutable;
use Qubus\ValueObjects\Person\Name;
use Qubus\ValueObjects\StringLiteral\StringLiteral;
use Qubus\ValueObjects\Web\EmailAddress;
use SensitiveParameter;

final class UserWasCreated extends AggregateChanged
{
    private UserId $id;

    private Username $login;

    private Name $name;

    private EmailAddress $email;

    private UserToken $token;

    private StringLiteral $password;

    private StringLiteral $url;

    private StringLiteral $timezone;

    private StringLiteral $dateFormat;

    private StringLiteral $timeFormat;

    private StringLiteral $locale;

    private DateTimeInterface $registered;

    private ArrayLiteral $meta;

    /**
     * @throws Exception
     */
    public static function withData(
        UserId $id,
        Username $login,
        Name $name,
        EmailAddress $email,
        #[SensitiveParameter] UserToken $token,
        #[SensitiveParameter] StringLiteral $password,
        StringLiteral $url,
        StringLiteral $timezone,
        StringLiteral $dateFormat,
        StringLiteral $timeFormat,
        StringLiteral $locale,
        DateTimeInterface $registered,
        ?ArrayLiteral $meta = null,
    ): UserWasCreated|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'user_id' => $id->toNative(),
                'user_login' => $login->toNative(),
                'user_fname' => $name->getFirstName()->toNative(),
                'user_mname' => $name->getMiddleName()->toNative(),
                'user_lname' => $name->getLastName()->toNative(),
                'user_email' => $email->toNative(),
                'user_token' => $token->toNative(),
                'user_pass' => Password::hash($password->toNative()),
                'user_url' => $url->toNative(),
                'user_timezone' => $timezone->toNative(),
                'user_date_format' => $dateFormat->toNative(),
                'user_time_format' => $timeFormat->toNative(),
                'user_locale' => $locale->toNative(),
                'user_registered' => (string) $registered,
                'meta' => $meta->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'user',
            ]
        );

        $event->id = $id;
        $event->login = $login;
        $event->name = $name;
        $event->email = $email;
        $event->token = $token;
        $event->password = $password;
        $event->url = $url;
        $event->timezone = $timezone;
        $event->dateFormat = $dateFormat;
        $event->timeFormat = $timeFormat;
        $event->locale = $locale;
        $event->registered = $registered;
        $event->meta = $meta;

        return $event;
    }

    /**
     * @throws TypeException
     */
    public function userId(): UserId|AggregateId
    {
        if (!isset($this->id)) {
            $this->id = UserId::fromString(userId: $this->aggregateId()->__toString());
        }

        return $this->id;
    }

    /**
     * @throws TypeException
     */
    public function userLogin(): Username
    {
        if (!isset($this->login)) {
            $this->login = Username::fromString($this->payload()['user_login']);
        }

        return $this->login;
    }

    public function name(): Name
    {
        if (!isset($this->name)) {
            $this->name = Name::fromNative(
                $this->payload()['user_fname'],
                $this->payload()['user_mname'],
                $this->payload()['user_lname']
            );
        }

        return $this->name;
    }

    public function userEmail(): EmailAddress
    {
        if (!isset($this->email)) {
            $this->email = EmailAddress::fromNative($this->payload()['user_email']);
        }

        return $this->email;
    }

    /**
     * @throws TypeException
     */
    public function userToken(): UserToken
    {
        if (!isset($this->token)) {
            $this->token = UserToken::fromString($this->payload()['user_token']);
        }

        return $this->token;
    }

    public function userPass(): StringLiteral
    {
        if (!isset($this->password)) {
            $this->password = StringLiteral::fromNative($this->payload()['user_pass']);
        }

        return $this->password;
    }

    public function userUrl(): StringLiteral
    {
        if (!isset($this->url)) {
            $this->url = StringLiteral::fromNative($this->payload()['user_url']);
        }

        return $this->url;
    }

    public function userTimezone(): StringLiteral
    {
        if (!isset($this->timezone)) {
            $this->timezone = StringLiteral::fromNative($this->payload()['user_timezone']);
        }

        return $this->timezone;
    }

    public function userDateFormat(): StringLiteral
    {
        if (!isset($this->dateFormat)) {
            $this->dateFormat = StringLiteral::fromNative($this->payload()['user_date_format']);
        }

        return $this->dateFormat;
    }

    public function userTimeFormat(): StringLiteral
    {
        if (!isset($this->timeFormat)) {
            $this->timeFormat = StringLiteral::fromNative($this->payload()['user_time_format']);
        }

        return $this->timeFormat;
    }

    public function userLocale(): StringLiteral
    {
        if (!isset($this->locale)) {
            $this->locale = StringLiteral::fromNative($this->payload()['user_locale']);
        }

        return $this->locale;
    }

    public function userRegistered(): DateTimeInterface
    {
        if (!isset($this->registered)) {
            $this->registered = QubusDateTimeImmutable::createFromInterface(
                new DateTime($this->payload()['user_registered'])->getDateTime()
            );
        }

        return $this->registered;
    }

    public function usermeta(): ArrayLiteral
    {
        if (!isset($this->meta)) {
            $this->meta = ArrayLiteral::fromNative($this->payload()['meta']);
        }

        return $this->meta;
    }
}
