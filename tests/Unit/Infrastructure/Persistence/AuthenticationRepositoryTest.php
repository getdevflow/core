<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Repository\AuthenticationRepository;
use Codefy\Framework\Support\Password;
use Qubus\Config\Collection;
use Qubus\Expressive\Connection\Pdo\Sqlite;

it('uses configured password and token columns and upgrades weak hashes', function (): void {
    $connection = new Sqlite(['dsn' => 'sqlite::memory:']);
    $connection->pdo->exec('CREATE TABLE accounts (login TEXT, secret TEXT, session_token TEXT)');
    $oldHash = password_hash('correct-password', PASSWORD_BCRYPT, ['cost' => 4]);
    $connection->pdo->prepare('INSERT INTO accounts VALUES (?, ?, ?)')->execute(['alice', $oldHash, 'current-token']);
    $config = new Collection([]);
    $config->setConfigKey('auth', ['pdo' => ['table' => 'accounts', 'fields' => [
        'identity' => 'login', 'password' => 'secret', 'token' => 'session_token', 'role' => 'role',
    ]]]);
    $repository = new AuthenticationRepository($connection, $config);
    expect($repository->authenticate('alice', 'incorrect'))->toBeNull();
    expect($repository->authenticate('alice', 'correct-password')->token)->toBe('current-token');
    $newHash = $connection->pdo->query('SELECT secret FROM accounts')->fetchColumn();
    expect($newHash)->not->toBe($oldHash)->and(Password::verify('correct-password', $newHash))->toBeTrue()
        ->and(Password::needsRehash($newHash))->toBeFalse()
        ->and($repository->find('current-token')->login)->toBe('alice')
        ->and($repository->find('unknown'))->toBeFalse();
});
