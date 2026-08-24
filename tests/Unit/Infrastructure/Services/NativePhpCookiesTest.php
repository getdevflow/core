<?php

declare(strict_types=1);

use App\Infrastructure\Services\NativePhpCookies;

afterEach(function (): void {
    unset($_COOKIE['TEST_COOKIE']);
});

it('treats array-shaped cookie input as invalid', function (): void {
    $_COOKIE['TEST_COOKIE'] = ['data' => 'attacker-controlled'];

    expect(new NativePhpCookies()->get('TEST_COOKIE'))->toBe('');
});

it('rejects path traversal before cookie data can reach the filesystem', function (): void {
    $_COOKIE['TEST_COOKIE'] = 'exp=9999999999&data=../../outside&digest=' . str_repeat('a', 64);
    $cookies = new NativePhpCookies();

    expect($cookies->getCookieData('TEST_COOKIE'))->toBe('')
        ->and($cookies->verifySecureCookie('TEST_COOKIE'))->toBeFalse();
});

it('rejects oversized cookies before parsing', function (): void {
    $_COOKIE['TEST_COOKIE'] = str_repeat('a', 4097);

    expect(new NativePhpCookies()->getCookieData('TEST_COOKIE'))->toBe('');
});
