<?php

declare(strict_types=1);

use function App\Shared\Helpers\cms_safe_redirect_url;

it('accepts local and same-origin redirect destinations', function (string $candidate): void {
    expect(cms_safe_redirect_url($candidate, 'https://cms.example/admin'))->toBe($candidate);
})->with([
    '/admin/content?status=draft',
    'https://cms.example/admin/users',
]);

it('rejects external and structurally ambiguous redirects', function (mixed $candidate): void {
    expect(cms_safe_redirect_url($candidate, 'https://cms.example/admin'))
        ->toBe('https://cms.example/admin');
})->with([
    'https://evil.example/phish',
    '//evil.example/phish',
    '%2F%2Fevil.example/phish',
    'javascript:alert(1)',
    'admin/relative',
    "/admin\r\nLocation: https://evil.example",
    null,
]);
