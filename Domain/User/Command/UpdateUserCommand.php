<?php

declare(strict_types=1);

namespace App\Domain\User\Command;

use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\Username;
use App\Domain\User\ValueObject\UserToken;
use App\Shared\ValueObject\ArrayLiteral;
use Codefy\CommandBus\PropertyCommand;
use DateTimeInterface;
use Qubus\ValueObjects\StringLiteral\StringLiteral;
use Qubus\ValueObjects\Web\EmailAddress;

final class UpdateUserCommand extends PropertyCommand
{
    public UserId $id;

    public StringLiteral $fname;

    public StringLiteral $mname;

    public StringLiteral $lname;

    public EmailAddress $email;

    public Username $login;

    public UserToken $token;

    public StringLiteral $url;

    public StringLiteral $bio;

    public StringLiteral $timezone;

    public StringLiteral $dateFormat;

    public StringLiteral $timeFormat;

    public StringLiteral $locale;

    public DateTimeInterface $modified;

    public StringLiteral $activationKey;

    public ArrayLiteral $attributes;
}
