<?php

declare(strict_types=1);

namespace App\Domain\User\Dto;

use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\Username;
use App\Domain\User\ValueObject\UserToken;
use App\Shared\ValueObject\ArrayLiteral;
use Codefy\Framework\Dto\DataTransformer;
use Codefy\Framework\Validation\DataValidator;
use DateTimeInterface;
use Qubus\Support\DateTime\QubusDateTimeImmutable;
use Qubus\ValueObjects\StringLiteral\StringLiteral;
use Qubus\ValueObjects\Web\EmailAddress;

final readonly class StoreUserData implements DataTransformer
{
    public function __construct(
        public ?UserId $id = null,
        public ?StringLiteral $fname = null,
        public ?StringLiteral $mname = null,
        public ?StringLiteral $lname = null,
        public ?EmailAddress $email = null,
        public ?Username $login = null,
        public ?UserToken $token = null,
        public ?StringLiteral $pass = null,
        public ?StringLiteral $status = null,
        public ?StringLiteral $role = null,
        public ?DateTimeInterface $registered = null,
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
        $registered = QubusDateTimeImmutable::now();

        return new self(
            id: UserId::fromString($data->string(key: 'id')),
            fname: new StringLiteral($data->string(key: 'fname')),
            mname: new StringLiteral($data->string(key: 'mname', default: '')),
            lname: new StringLiteral($data->string(key: 'lname')),
            email: new EmailAddress($data->string(key: 'email')),
            login: new Username($data->string(key: 'login')),
            token: UserToken::fromString($data->string(key: 'token')),
            pass: new StringLiteral($data->string(key: 'pass')),
            status: new StringLiteral($data->string(key: 'status')),
            role: new StringLiteral($data->string(key: 'role')),
            registered: $registered,
            attribute: new ArrayLiteral($data->array(key: 'user_field', default: [])),
        );
    }
}
