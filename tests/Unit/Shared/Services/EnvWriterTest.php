<?php

declare(strict_types=1);

use App\Shared\Services\EnvWriter;

beforeEach(function (): void {
    $this->envFile = tempnam(sys_get_temp_dir(), 'devflow-env-');
    file_put_contents($this->envFile, "APP_ENV=production\nEXISTING=value\n");
});

afterEach(function (): void {
    if (is_file($this->envFile)) {
        unlink($this->envFile);
    }
});

it('replaces existing values and appends new values', function (): void {
    $writer = new EnvWriter($this->envFile);

    $writer->set('EXISTING', 'updated');
    $writer->set('NEW_VALUE', 'hello world');

    expect(file_get_contents($this->envFile))
        ->toBe("APP_ENV=production\nEXISTING=updated\nNEW_VALUE=\"hello world\"\n");
});

it('quotes control characters so values cannot inject new variables', function (): void {
    (new EnvWriter($this->envFile))->set('SAFE_VALUE', "first\nINJECTED=yes\r\n\$EXPAND");

    $contents = file_get_contents($this->envFile);

    expect($contents)
        ->toContain('SAFE_VALUE="first\\nINJECTED=yes\\r\\n\\$EXPAND"')
        ->not->toMatch('/^INJECTED=/m');
});

it('rejects invalid environment variable names', function (): void {
    (new EnvWriter($this->envFile))->set("BAD\nKEY", 'value');
})->throws(RuntimeException::class, 'Invalid environment variable name');
