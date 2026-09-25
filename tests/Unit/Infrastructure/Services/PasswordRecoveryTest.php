<?php

declare(strict_types=1);

use App\Infrastructure\Services\User\PasswordRecovery;
use Codefy\Framework\Support\Password;
use Qubus\Config\Collection;
use Qubus\Expressive\Connection\Pdo\Sqlite;
use Qubus\Expressive\QueryBuilder;

beforeEach(function (): void {
    $this->connection = new Sqlite(['dsn' => 'sqlite::memory:']);
    $database = new QueryBuilder($this->connection);
    $this->connection->pdo->exec('CREATE TABLE users (
        user_id TEXT PRIMARY KEY, user_login TEXT, user_email TEXT,
        user_pass TEXT, user_token TEXT, user_activation_key TEXT
    )');
    $this->hash = Password::hash('original-password');
    $this->connection->pdo->prepare('INSERT INTO users VALUES (?, ?, ?, ?, ?, NULL)')
        ->execute(['user-1', 'alice', 'alice@example.com', $this->hash, 'original-token']);
    $config = new Collection([]);
    $config->setConfigKey('auth', ['pdo' => ['table' => 'users'], 'password_min_length' => 12]);
    $this->recovery = new PasswordRecovery($database, $config);
});

it('leaves credentials unchanged until proof is supplied and consumes proof only once', function (): void {
    $data = $this->recovery->request('alice@example.com');
    $user = $this->connection->pdo->query('SELECT * FROM users')->fetch(PDO::FETCH_ASSOC);
    expect($user['user_pass'])->toBe($this->hash)->and($user['user_token'])->toBe('original-token')
        ->and($user['user_activation_key'])->not->toContain($data['token']);
    expect($this->recovery->reset('user-1', str_repeat('0', 64), 'replacement-password'))->toBeFalse()
        ->and($this->recovery->reset('other-user', $data['token'], 'replacement-password'))->toBeFalse()
        ->and($this->recovery->reset('user-1', $data['token'], 'short'))->toBeFalse()
        ->and($this->recovery->reset('user-1', $data['token'], 'replacement-password'))->toBeTrue()
        ->and($this->recovery->reset('user-1', $data['token'], 'another-password'))->toBeFalse();
    $user = $this->connection->pdo->query('SELECT * FROM users')->fetch(PDO::FETCH_ASSOC);
    expect(Password::verify('replacement-password', $user['user_pass']))->toBeTrue()
        ->and($user['user_token'])->not->toBe('original-token')->and($user['user_activation_key'])->toBeNull()
        ->and(\App\Domain\User\ValueObject\UserToken::fromString($user['user_token'])->toNative())
        ->toBe($user['user_token']);
});

it('rejects expired and superseded recovery links', function (): void {
    $old = $this->recovery->request('alice@example.com');
    $current = $this->recovery->request('alice@example.com');
    expect($this->recovery->reset('user-1', $old['token'], 'replacement-password'))->toBeFalse();
    $this->connection->pdo->prepare('UPDATE users SET user_activation_key = ?')->execute([
        hash('sha256', $current['token']) . ':' . (time() - 1),
    ]);
    expect($this->recovery->reset('user-1', $current['token'], 'replacement-password'))->toBeFalse()
        ->and($this->recovery->request('missing@example.com'))->toBeNull()
        ->and($this->recovery->request('invalid-email'))->toBeNull();
});
