<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Qubus\Http\Session\PhpSession;
use Qubus\Http\Session\SessionException;

final class FormState
{
    private const string OLD_INPUT_KEY = '_devflow_old_input';
    private const string FORM_ERRORS_KEY = '_devflow_form_errors';

    /** @var array<string,mixed>|null */
    private ?array $oldInput = null;

    /** @var array<string,string|array<int,string>>|null */
    private ?array $errors = null;

    public function __construct(
        private readonly PhpSession $session
    ) {
    }

    /**
     * @param array<string,mixed> $input
     * @param array<int,string> $except
     * @throws SessionException
     */
    public function flashInput(
        array $input,
        array $except = ['password', 'password_confirmation', '_token', 'csrf_token']
    ): void {
        foreach ($except as $key) {
            unset($input[$key]);
        }

        $this->session->set(self::OLD_INPUT_KEY, $input);
        $this->oldInput = $input;
    }

    /**
     * @param array<string,string|array<int,string>> $errors
     * @throws SessionException
     */
    public function flashErrors(array $errors): void
    {
        $this->session->set(self::FORM_ERRORS_KEY, $errors);
        $this->errors = $errors;
    }

    public function old(string $key, mixed $default = ''): mixed
    {
        return $this->arrayGet($this->oldInput(), $key, $default);
    }

    public function hasOld(string $key): bool
    {
        return $this->old($key, null) !== null;
    }

    /**
     * @return array<string,mixed>
     */
    public function oldInput(): array
    {
        if ($this->oldInput !== null) {
            return $this->oldInput;
        }

        if (!$this->session->has(self::OLD_INPUT_KEY)) {
            $this->oldInput = [];
            return $this->oldInput;
        }

        $this->oldInput = (array) $this->session->get(self::OLD_INPUT_KEY);
        $this->session->unsetSession(self::OLD_INPUT_KEY);

        return $this->oldInput;
    }

    public function error(string $key): string
    {
        $error = $this->errors()[$key] ?? null;

        if (is_array($error)) {
            $error = reset($error);
        }

        return $error ? (string) $error : '';
    }

    public function hasError(string $key): bool
    {
        return $this->error($key) !== '';
    }

    /**
     * @return array<string,string|array<int,string>>
     */
    public function errors(): array
    {
        if ($this->errors !== null) {
            return $this->errors;
        }

        if (!$this->session->has(self::FORM_ERRORS_KEY)) {
            $this->errors = [];
            return $this->errors;
        }

        $this->errors = (array) $this->session->get(self::FORM_ERRORS_KEY);
        $this->session->unsetSession(self::FORM_ERRORS_KEY);

        return $this->errors;
    }

    public function clear(): void
    {
        if ($this->session->has(self::OLD_INPUT_KEY)) {
            $this->session->unsetSession(self::OLD_INPUT_KEY);
        }

        if ($this->session->has(self::FORM_ERRORS_KEY)) {
            $this->session->unsetSession(self::FORM_ERRORS_KEY);
        }

        $this->oldInput = [];
        $this->errors = [];
    }

    /**
     * @param array<string,mixed> $data
     */
    private function arrayGet(array $data, string $key, mixed $default = null): mixed
    {
        foreach (explode('.', $key) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return $default;
            }

            $data = $data[$segment];
        }

        return $data;
    }
}
