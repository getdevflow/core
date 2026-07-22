<?php

declare(strict_types=1);

namespace App\Shared\Services\Security;

final readonly class ComposerAuditResult
{
    public function __construct(
        public string $status,
        public string $checkedAt,
        public int $advisoryCount = 0,
        public array $packages = [],
        public ?string $message = null,
        public ?string $fingerprint = null,
        public int $exitCode = 0,
    ) {
    }

    public function hasAdvisories(): bool
    {
        return $this->status === 'vulnerable' && $this->advisoryCount > 0;
    }

    public function failed(): bool
    {
        return $this->status === 'failed';
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'checked_at' => $this->checkedAt,
            'advisory_count' => $this->advisoryCount,
            'packages' => $this->packages,
            'message' => $this->message,
            'fingerprint' => $this->fingerprint,
            'exit_code' => $this->exitCode,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            status: (string) ($data['status'] ?? 'unknown'),
            checkedAt: (string) ($data['checked_at'] ?? ''),
            advisoryCount: (int) ($data['advisory_count'] ?? 0),
            packages: is_array($data['packages'] ?? null) ? $data['packages'] : [],
            message: isset($data['message']) ? (string) $data['message'] : null,
            fingerprint: isset($data['fingerprint']) ? (string) $data['fingerprint'] : null,
            exitCode: (int) ($data['exit_code'] ?? 0),
        );
    }
}
