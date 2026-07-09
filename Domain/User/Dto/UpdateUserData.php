<?php

declare(strict_types=1);

namespace App\Domain\User\Dto;

use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\Username;
use App\Shared\ValueObject\ArrayLiteral;
use Codefy\Framework\Dto\DataTransformer;
use Codefy\Framework\Validation\DataValidator;
use DateTimeInterface;
use Qubus\Support\DateTime\QubusDateTimeImmutable;
use Qubus\ValueObjects\StringLiteral\StringLiteral;
use Qubus\ValueObjects\Web\EmailAddress;

final readonly class UpdateUserData implements DataTransformer
{
    public function __construct(
        public ?UserId $id = null,
        public ?StringLiteral $fname = null,
        public ?StringLiteral $mname = null,
        public ?StringLiteral $lname = null,
        public ?EmailAddress $email = null,
        public ?Username $login = null,
        public ?StringLiteral $url = null,
        public ?StringLiteral $bio = null,
        public ?StringLiteral $status = null,
        public ?StringLiteral $role = null,
        public ?StringLiteral $timezone = null,
        public ?StringLiteral $dateFormat = null,
        public ?StringLiteral $timeFormat = null,
        public ?StringLiteral $locale = null,
        public ?DateTimeInterface $modified = null,
        public ?ArrayLiteral $attribute = null,
    ) {
    }

    /**
     * @param DataValidator $data
     * @return DataTransformer
     * @throws \Qubus\Exception\Data\TypeException
     */
    public static function fromValidatedData(DataValidator $data): DataTransformer
    {
        $modified = QubusDateTimeImmutable::now();

        return new self(
            id: UserId::fromString($data->string(key: 'id')),
            fname: new StringLiteral($data->string(key: 'fname')),
            mname: new StringLiteral($data->string(key: 'mname', default: '')),
            lname: new StringLiteral($data->string(key: 'lname')),
            email: new EmailAddress($data->string(key: 'email')),
            login: new Username($data->string(key: 'login')),
            url: new StringLiteral($data->string(key: 'url')),
            bio: new StringLiteral($data->string(key: 'bio')),
            status: new StringLiteral($data->string(key: 'status')),
            role: new StringLiteral($data->string(key: 'role')),
            timezone: new StringLiteral($data->string(key: 'timezone')),
            dateFormat: new StringLiteral($data->string(key: 'dateFormat')),
            timeFormat: new StringLiteral($data->string(key: 'timeFormat')),
            locale: new StringLiteral($data->string(key: 'locale')),
            modified: $modified,
            attribute: new ArrayLiteral($data->array(key: 'user_field', default: [])),
        );
    }
}
