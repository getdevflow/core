<?php

declare(strict_types=1);

use App\Infrastructure\Services\Queue\EmailChangeNotification;
use App\Infrastructure\Services\Queue\NewAccountNotification;
use App\Infrastructure\Services\Queue\ResetPasswordNotification;
use App\Infrastructure\Services\Content\Workflow\Queue\ContentWorkflowEmailNotification;
use Codefy\Framework\Application;
use Codefy\Framework\Proxy\Codefy;
use Codefy\Framework\Queue\JobSerializer;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Qubus\Config\Collection;

it('round trips allowlisted notifications without exposing their contents', function (string $class, array $data): void {
    $previous = isset(Codefy::$PHP) ? Codefy::$PHP : null;
    $key = Key::createNewRandomKey();
    $config = new Collection([]);
    $config->setConfigKey('app', ['crypto_key' => $key->saveToAsciiSafeString()]);
    $app = new ReflectionClass(Application::class)->newInstanceWithoutConstructor();
    new ReflectionProperty(Application::class, 'configContainer')->setValue($app, $config);
    Codefy::$PHP = $app;
    try {
        $job = new $class($data + ['pass' => 'discard-legacy-password', 'extra' => 'discard-extra-field']);
        $json = JobSerializer::encode($job);
        expect($json)->not->toContain($data['email']);
        $restored = JobSerializer::decode($json, [$class]);
        expect($restored)->toBeInstanceOf($class)
            ->and($restored->name)->toBe($job->name)
            ->and($restored->executions)->toBe($job->executions);
        $decoded = json_decode(Crypto::decrypt($restored->toPayload()['encrypted'], $key), true);
        expect($decoded)->toBe($data);
        expect(fn () => JobSerializer::decode($json, []))->toThrow(UnexpectedValueException::class);
        expect(fn () => $class::fromPayload(['encrypted' => Crypto::encrypt('[]', $key)]))
            ->toThrow(InvalidArgumentException::class);
    } finally {
        if ($previous !== null) {
            Codefy::$PHP = $previous;
        }
    }
})->with([
    [NewAccountNotification::class, ['login' => 'alice', 'sitename' => 'CMS', 'email' => 'alice@example.com', 'url' => 'https://cms.example/login']],
    [EmailChangeNotification::class, ['login' => 'alice', 'admin' => 'admin@example.com', 'sitename' => 'CMS', 'email' => 'alice@example.com', 'url' => 'https://cms.example/login']],
    [ResetPasswordNotification::class, ['login' => 'alice', 'sitename' => 'CMS', 'email' => 'alice@example.com', 'url' => 'https://cms.example/login']],
    [ContentWorkflowEmailNotification::class, ['email' => 'alice@example.com', 'user' => 'Alice', 'sitename' => 'CMS', 'notification_type' => 'Review', 'notification_title' => 'Review requested', 'notification_message' => 'Review this page', 'action_url' => 'https://cms.example/review', 'action_label' => 'Review']],
]);

it('rejects invalid notification fields before enqueueing', function (): void {
    expect(fn () => new NewAccountNotification(['email' => []]))->toThrow(InvalidArgumentException::class);
    expect(fn () => ResetPasswordNotification::fromPayload(['data' => []]))->toThrow(InvalidArgumentException::class);
});
