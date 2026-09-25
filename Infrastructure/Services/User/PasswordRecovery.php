<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\User;

use Codefy\Framework\Support\Password;
use App\Domain\User\ValueObject\UserToken;
use Qubus\Config\ConfigContainer;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Expressive\Database;
use Random\RandomException;

/** Single-use recovery credentials; requesting recovery never changes the password. */
final readonly class PasswordRecovery
{
    public function __construct(private Database $dfdb, private ConfigContainer $config)
    {
    }

    /**
     * @param string $email
     * @return array{id:string, login:string, email:string, token:string}|null
     * @throws TypeException
     * @throws RandomException
     */
    public function request(string $email): ?array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        $table = $this->identifier($this->config->string('auth.pdo.table'));
        $stmt = $this->dfdb->getConnection()->pdo->prepare(
            "SELECT user_id, user_login, user_email FROM {$table} WHERE user_email = ?"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$user) {
            return null;
        }
        $lifetime = $this->config->integer('auth.password_reset_lifetime', 3600);
        if ($lifetime <= 0) {
            throw new \InvalidArgumentException('Password recovery lifetime must be positive.');
        }
        $token = bin2hex(random_bytes(32));
        $credential = hash('sha256', $token) . ':' . (time() + $lifetime);
        $stmt = $this->dfdb->getConnection()->pdo->prepare("UPDATE {$table} SET user_activation_key = ? WHERE user_id = ?");
        $stmt->execute([$credential, $user['user_id']]);

        return [
            'id' => $user['user_id'], 'login' => $user['user_login'],
            'email' => $user['user_email'], 'token' => $token,
        ];
    }

    /**
     * @throws TypeException
     * @throws Exception
     */
    public function reset(
        string $id,
        #[\SensitiveParameter] string $token,
        #[\SensitiveParameter] string $password
    ): bool {
        if (
            !preg_match('/\A[a-f0-9]{64}\z/D', $token)
            || mb_strlen($password) < $this->config->integer('auth.password_min_length', 12)
        ) {
            return false;
        }
        $table = $this->identifier($this->config->string('auth.pdo.table'));
        $passwordColumn = $this->identifier($this->config->string('auth.pdo.fields.password', 'user_pass'));
        $tokenColumn = $this->identifier($this->config->string('auth.pdo.fields.token', 'user_token'));
        $stmt = $this->dfdb->getConnection()->pdo->prepare("SELECT user_activation_key FROM {$table} WHERE user_id = ?");
        $stmt->execute([$id]);
        $credential = $stmt->fetchColumn();
        if (!is_string($credential)) {
            return false;
        }
        $parts = explode(':', $credential);
        if (
            count($parts) !== 2 || !ctype_digit($parts[1]) || (int) $parts[1] <= time()
            || !hash_equals($parts[0], hash('sha256', $token))
        ) {
            return false;
        }

        // Compare and consume in one statement so concurrent replays cannot both succeed.
        $stmt = $this->dfdb->getConnection()->pdo->prepare(
            "UPDATE {$table} SET {$passwordColumn} = ?, {$tokenColumn} = ?, user_activation_key = NULL "
            . 'WHERE user_id = ? AND user_activation_key = ?'
        );
        $stmt->execute([Password::hash($password), new UserToken()->toNative(), $id, $credential]);
        return $stmt->rowCount() === 1;
    }

    private function identifier(string $value): string
    {
        return $this->dfdb->getConnection()->quoteIdentifier($value);
    }
}
