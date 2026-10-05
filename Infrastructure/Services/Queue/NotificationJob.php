<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Queue;

use Codefy\Framework\Queue\SerializableJob;
use Codefy\Framework\Queue\SimpleQueue;
use Defuse\Crypto\Exception\BadFormatException;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
use InvalidArgumentException;
use App\Application\Devflow;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use JsonException;
use Qubus\Exception\Data\TypeException;

/** Explicit, encrypted payloads keep notification credentials out of queue JSON. */
abstract class NotificationJob extends SimpleQueue implements SerializableJob
{
    protected const array FIELDS = [];

    protected array $data;

    final public function __construct(array $data)
    {
        $this->data = [];
        foreach (static::FIELDS as $field) {
            if (!is_string($data[$field] ?? null)) {
                throw new InvalidArgumentException('Invalid notification field: ' . $field);
            }
            $this->data[$field] = $data[$field];
        }
        if (!filter_var($this->data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid notification recipient.');
        }
    }

    /**
     * @return array
     * @throws BadFormatException
     * @throws EnvironmentIsBrokenException
     * @throws JsonException
     * @throws TypeException
     */
    public function toPayload(): array
    {
        return ['encrypted' => Crypto::encrypt(json_encode($this->data, JSON_THROW_ON_ERROR), self::encryptionKey())];
    }

    /**
     * @throws TypeException
     * @throws EnvironmentIsBrokenException
     * @throws BadFormatException
     */
    private static function encryptionKey(): Key
    {
        return Key::loadFromAsciiSafeString(Devflow::$PHP->configContainer->string('app.crypto_key'));
    }

    /**
     * @throws TypeException
     * @throws EnvironmentIsBrokenException
     * @throws JsonException
     * @throws WrongKeyOrModifiedCiphertextException
     * @throws BadFormatException
     */
    public static function fromPayload(array $payload): static
    {
        if (!is_string($payload['encrypted'] ?? null) || $payload['encrypted'] === '') {
            throw new InvalidArgumentException('Invalid encrypted notification payload.');
        }
        $data = json_decode(
            Crypto::decrypt($payload['encrypted'], self::encryptionKey()),
            true,
            16,
            JSON_THROW_ON_ERROR
        );
        if (!is_array($data)) {
            throw new InvalidArgumentException('Invalid notification data.');
        }
        return new static($data);
    }
}
