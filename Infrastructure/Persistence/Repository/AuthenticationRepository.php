<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use Codefy\Framework\Auth\Repository\AuthUserRepository;
use Codefy\Framework\Auth\UserSession;
use Codefy\Framework\Support\Password;
use Qubus\Config\ConfigContainer;
use Qubus\Exception\Exception;
use Qubus\Expressive\Connection;
use Qubus\Http\Session\SessionEntity;
use Qubus\Support\DateTime\QubusDateTimeImmutable;

use function sprintf;

class AuthenticationRepository implements AuthUserRepository
{
    public function __construct(private Connection $connection, protected ConfigContainer $config)
    {
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function authenticate(string $credential, #[\SensitiveParameter] ?string $password = null): ?SessionEntity
    {
        /** @var array{identity: string, role: string, token: string, password: string} $fields */
        $fields = $this->config->getConfigKey(key: 'auth.pdo.fields');

        /** @var string $table */
        $table = $this->config->getConfigKey('auth.pdo.table');

        $sql = sprintf(
            "SELECT * FROM %s WHERE %s = :identity",
            $table,
            $fields['identity']
        );

        $stmt = $this->connection->pdo->prepare($sql);
        if (false === $stmt) {
            return null;
        }

        $stmt->bindParam(':identity', $credential);
        $stmt->execute();

        /** @var object{'user_token': string|null, 'user_password': string}|null $result */
        $result = $stmt->fetchObject();
        if (! $result) {
            return null;
        }

        /** @var string $passwordHash */
        $passwordHash = ($result->{$fields['password']} ?? '');

        if (Password::verify(password: $password ?? '', hash: $passwordHash)) {
            $this->passwordRehash($table, $fields['identity'], $credential, $passwordHash, $password);

            $user = new UserSession();
            $user
                ->withToken($result->user_token);

            return $user;
        }

        return null;
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function find(string $token): bool|null|object
    {
        /** @var string $table */
        $table = $this->config->getConfigKey('auth.pdo.table');
        $sql = sprintf(
            "SELECT * FROM %s WHERE user_token = :token",
            $table,
        );

        $stmt = $this->connection->pdo->prepare($sql);
        if (false === $stmt) {
            return null;
        }

        $stmt->bindParam(':token', $token);
        $stmt->execute();

        return $stmt->fetchObject();
    }

    /**
     * @param string $table
     * @param string $identity
     * @param string $credential
     * @param string $hash
     * @param string $password
     * @return void
     * @throws Exception
     */
    private function passwordRehash(
        string $table,
        string $identity,
        string $credential,
        string $hash,
        string $password
    ): void {
        if (Password::needsRehash($hash)) {
            $newHash = Password::hash($password);

            $sql = sprintf(
                "UPDATE %s SET user_pass = :password, user_modified = :modified WHERE %s = :identity",
                $table,
                $identity
            );
            $stmt = $this->connection->pdo->prepare($sql);
            $data = [
                'password' => $newHash,
                'modified' => QubusDateTimeImmutable::now(),
                'identity' => $credential,
            ];
            $stmt->execute($data);
        }
    }
}
