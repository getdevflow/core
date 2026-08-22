<?php

declare(strict_types=1);

use App\Shared\Http\FormState;
use Qubus\Http\Session\PhpSession;

beforeEach(function (): void {
    $this->session = new class implements PhpSession {
        public array $data = [];

        public function has(string $name): bool
        {
            return array_key_exists($name, $this->data);
        }

        public function get(string $name): string|array
        {
            return $this->data[$name];
        }

        public function getAll(): array
        {
            return $this->data;
        }

        public function unsetSession(string $key): void
        {
            unset($this->data[$key]);
        }

        public function set(string $name, mixed $value): void
        {
            $this->data[$name] = $value;
        }
    };
    $this->state = new FormState($this->session);
});

it('flashes nested old input once while excluding credentials', function (): void {
    $this->state->flashInput([
        'profile' => ['name' => 'Joshua'],
        'password' => 'secret',
        '_token' => 'csrf',
    ]);
    $nextRequestState = new FormState($this->session);

    expect($nextRequestState->old('profile.name'))->toBe('Joshua')
        ->and($nextRequestState->hasOld('password'))->toBeFalse()
        ->and($this->session->getAll())->toBe([]);
});

it('returns the first error and consumes flashed errors', function (): void {
    $this->state->flashErrors(['title' => ['Title is required.', 'Title is too short.']]);
    $nextRequestState = new FormState($this->session);

    expect($nextRequestState->error('title'))->toBe('Title is required.')
        ->and($nextRequestState->hasError('title'))->toBeTrue()
        ->and($this->session->getAll())->toBe([]);
});
