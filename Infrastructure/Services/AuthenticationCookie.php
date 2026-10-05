<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use Codefy\Framework\Http\Middleware\Auth\AuthTokenAware;
use Defuse\Crypto\Exception\BadFormatException;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use JsonException;
use Qubus\Config\ConfigContainer;

/** Shares the framework cookie codec, including its server-side expiry checks. */
final class AuthenticationCookie
{
    use AuthTokenAware;

    public function __construct(private readonly ConfigContainer $configContainer)
    {
    }

    /**
     * @throws EnvironmentIsBrokenException
     * @throws JsonException
     * @throws BadFormatException
     */
    public function encode(string $token, int $lifetime): string
    {
        return $this->encryptAuthToken($token, $lifetime);
    }
}
