<?php

declare(strict_types=1);

use App\Infrastructure\Services\AuthenticationCookie;
use Codefy\Framework\Http\Middleware\Auth\UserCookieDecryptMiddleware;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Qubus\Config\Collection;
use Qubus\Http\Response;
use Qubus\Http\ServerRequest;

it('issues switching cookies that the framework authenticates and expires', function (): void {
    $key = Key::createNewRandomKey();
    $config = new Collection([]);
    $config->setConfigKey('app', ['crypto_key' => $key->saveToAsciiSafeString()]);
    $config->setConfigKey('auth', ['cookie_name' => 'AUTH']);
    $codec = new AuthenticationCookie($config);
    $handler = new class implements RequestHandlerInterface {
        public mixed $token = null;
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            $this->token = $request->getAttribute(UserCookieDecryptMiddleware::USER_COOKIE);
            return new Response();
        }
    };
    $middleware = new UserCookieDecryptMiddleware($config);
    $middleware->process(new ServerRequest()->withCookieParams(['AUTH' => $codec->encode('switch-token', 60)]), $handler);
    expect($handler->token)->toBe('switch-token');

    foreach ([Crypto::encrypt('legacy-token', $key), Crypto::encrypt(json_encode([
        'version' => 1, 'token' => 'expired-token', 'expires' => time() - 1,
    ]), $key)] as $invalid) {
        $middleware->process(new ServerRequest()->withCookieParams(['AUTH' => $invalid]), $handler);
        expect($handler->token)->toBeNull();
    }
    expect(fn () => $codec->encode('token', 0))->toThrow(InvalidArgumentException::class);
});
