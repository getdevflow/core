<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use JsonException;
use Qubus\Exception\Data\TypeException;
use Random\RandomException;
use RuntimeException;
use Throwable;

use function chmod;
use function Codefy\Framework\Helpers\config;
use function Codefy\Framework\Helpers\storage_path;
use function ctype_digit;
use function file_get_contents;
use function file_put_contents;
use function hash;
use function hash_equals;
use function hash_hmac;
use function hash_hmac_algos;
use function in_array;
use function is_dir;
use function is_file;
use function is_int;
use function is_object;
use function is_string;
use function json_decode;
use function json_encode;
use function mkdir;
use function parse_str;
use function preg_match;
use function random_int;
use function rtrim;
use function setcookie;
use function strlen;
use function strtolower;
use function time;
use function unlink;
use function urlencode;

use const DIRECTORY_SEPARATOR;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const LOCK_EX;

final class NativePhpCookies
{
    private const int MAX_COOKIE_LENGTH = 4096;

    public static function factory(): self
    {
        return new self();
    }

    /**
     * Generates a cryptographically random token and hashes it with the
     * configured cookie digest algorithm.
     *
     * @throws TypeException
     * @throws RandomException
     */
    public function token(int $length = 20): string
    {
        if ($length < 1) {
            throw new TypeException('Token length must be greater than zero.');
        }

        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }

        return hash($this->cookieAlgorithm(), $randomString);
    }

    /**
     * Sets a regular cookie. The expiration value remains a lifetime in
     * seconds for backwards compatibility.
     *
     * @throws TypeException
     */
    public function set(mixed $key, mixed $value, ?int $expires = null): bool
    {
        return setcookie(
            (string) $key,
            (string) $value,
            $this->cookieOptions(
                $expires === null
                    ? time() + config()->integer(key: 'cookies.lifetime')
                    : time() + $expires
            )
        );
    }

    public function get(string $key): string
    {
        $value = $_COOKIE[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * @param array{key?:mixed, exp?:mixed, id?:mixed, token?:mixed, remember?:mixed} $data
     * @throws JsonException
     * @throws RandomException
     * @throws TypeException
     */
    public function setSecureCookie(array $data): bool
    {
        $key = $data['key'] ?? '';
        $expires = $data['exp'] ?? 0;

        if (! is_string($key) || preg_match('/\A[A-Za-z0-9_-]+\z/D', $key) !== 1) {
            throw new TypeException('Secure cookie key contains invalid characters.');
        }

        if (! is_int($expires) || $expires <= time()) {
            throw new TypeException('Secure cookie expiration must be a future Unix timestamp.');
        }

        $token = $this->token();
        $value = $this->buildCookie($token, $expires);
        $directory = $this->cookieDirectory();

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create the secure cookie storage directory.');
        }

        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $file = $this->cookieFile($token);

        if (file_put_contents($file, $encoded, LOCK_EX) !== strlen($encoded)) {
            throw new RuntimeException('Unable to persist secure cookie data.');
        }

        chmod($file, 0600);

        $created = setcookie($key, $value, $this->cookieOptions($expires));

        if (! $created && is_file($file)) {
            unlink($file);
        }

        return $created;
    }

    /**
     * Returns server-side data only after the cookie MAC, expiration, cookie
     * name, and backing record have all been verified.
     *
     * @param string $key
     * @return object|false
     */
    public function getSecureCookie(string $key): object|false
    {
        if (! $this->verifySecureCookie($key)) {
            return false;
        }

        $parts = $this->cookieParts($key);

        return $parts === null ? false : $this->readSecureCookie($parts['data']);
    }

    /**
     * Unset a browser cookie.
     *
     * @throws TypeException
     */
    public function remove(string $key): bool
    {
        return setcookie(
            $key,
            '',
            $this->cookieOptions(time() - (432000 + config()->integer(key: 'cookies.lifetime')))
        );
    }

    /**
     * Revokes a valid secure cookie on the server and removes it from the
     * browser. Invalid client input is never used as a filesystem path.
     *
     * @throws TypeException
     */
    public function deleteSecureCookie(string $key): bool
    {
        $parts = $this->cookieParts($key);

        if ($parts !== null && $this->hasValidMac($parts)) {
            $this->deleteServerRecord($parts['data']);
        }

        return $this->remove($key);
    }

    /**
     * Generates a hardened cookie string with digest.
     *
     * @throws TypeException
     */
    public function buildCookie(mixed $data, mixed $expires): string
    {
        $string = sprintf('exp=%s&data=%s', urlencode((string) $expires), urlencode((string) $data));
        $mac = hash_hmac($this->cookieAlgorithm(), $string, $this->cookieSecret());

        return $string . '&digest=' . urlencode($mac);
    }

    /**
     * Extracts a scalar value from a structurally valid secure cookie.
     */
    public function getCookieVars(string $key, mixed $str): string
    {
        if (! is_string($str)) {
            return '';
        }

        $parts = $this->cookieParts($key);

        return $parts[$str] ?? '';
    }

    public function getCookieData(string $key): string
    {
        return $this->getCookieVars($key, 'data');
    }

    /**
     * Verifies the MAC, expiry, cookie name, and server-side session record.
     *
     * @param string $key
     * @return bool
     */
    public function verifySecureCookie(string $key): bool
    {
        $parts = $this->cookieParts($key);

        if ($parts === null || ! $this->hasValidMac($parts)) {
            return false;
        }

        if ((int) $parts['exp'] < time()) {
            $this->deleteServerRecord($parts['data']);

            return false;
        }

        $data = $this->readSecureCookie($parts['data']);

        if ($data === false || ! isset($data->key, $data->exp)) {
            return false;
        }

        return is_string($data->key)
        && hash_equals($key, $data->key)
        && (int) $data->exp === (int) $parts['exp'];
    }

    /**
     * @return array{exp:string, data:string, digest:string}|null
     */
    private function cookieParts(string $key): ?array
    {
        $cookie = $this->get($key);

        if ($cookie === '' || strlen($cookie) > self::MAX_COOKIE_LENGTH) {
            return null;
        }

        $parts = [];
        parse_str($cookie, $parts);

        if (
            ! isset($parts['exp'], $parts['data'], $parts['digest'])
            || ! is_string($parts['exp'])
            || ! is_string($parts['data'])
            || ! is_string($parts['digest'])
            || ! ctype_digit($parts['exp'])
            || preg_match('/\A[a-f0-9]{32,128}\z/D', $parts['data']) !== 1
            || preg_match('/\A[a-f0-9]{32,256}\z/D', $parts['digest']) !== 1
        ) {
            return null;
        }

        return [
            'exp' => $parts['exp'],
            'data' => $parts['data'],
            'digest' => $parts['digest'],
        ];
    }

    /**
     * @param array{exp:string, data:string, digest:string} $parts
     */
    private function hasValidMac(array $parts): bool
    {
        $payload = sprintf(
            'exp=%s&data=%s',
            urlencode($parts['exp']),
            urlencode($parts['data'])
        );

        try {
            $hash = hash_hmac($this->cookieAlgorithm(), $payload, $this->cookieSecret());
        } catch (Throwable) {
            return false;
        }

        return hash_equals($hash, $parts['digest']);
    }

    private function readSecureCookie(string $token): object|false
    {
        $file = $this->cookieFile($token);

        if (! is_file($file)) {
            return false;
        }

        $contents = file_get_contents($file);

        if ($contents === false) {
            return false;
        }

        try {
            $data = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        return is_object($data) ? $data : false;
    }

    private function deleteServerRecord(string $token): void
    {
        $file = $this->cookieFile($token);

        if (is_file($file)) {
            unlink($file);
        }
    }

    private function cookieFile(string $token): string
    {
        return $this->cookieDirectory() . DIRECTORY_SEPARATOR . 'cookie.' . $token;
    }

    private function cookieDirectory(): string
    {
        return rtrim(storage_path('app/cookies'), '/\\');
    }

    /**
     * @return array{expires:int, path:string, domain:string, secure:bool, httponly:bool, samesite:string}
     * @throws TypeException
     */
    private function cookieOptions(int $expires): array
    {
        $secure = config()->boolean(key: 'cookies.secure');
        $sameSite = match (strtolower((string) config(key: 'cookies.samesite', default: 'Lax'))) {
            'strict' => 'Strict',
            'none' => $secure ? 'None' : 'Lax',
            default => 'Lax',
        };

        return [
            'expires' => $expires,
            'path' => (string) config(key: 'cookies.path'),
            'domain' => (string) config(key: 'cookies.domain'),
            'secure' => $secure,
            'httponly' => config()->boolean(key: 'cookies.httponly', default: true),
            'samesite' => $sameSite,
        ];
    }

    /**
     * @throws TypeException
     */
    private function cookieAlgorithm(): string
    {
        $algorithm = config(key: 'cookies.crypt');

        if (! is_string($algorithm) || ! in_array($algorithm, hash_hmac_algos(), true)) {
            throw new TypeException('Cookie digest algorithm is missing or unsupported.');
        }

        return $algorithm;
    }

    /**
     * @throws TypeException
     */
    private function cookieSecret(): string
    {
        $secret = config(key: 'cookies.secret_key');

        if (! is_string($secret) || $secret === '') {
            throw new TypeException('Cookie secret key must not be empty.');
        }

        return $secret;
    }
}
