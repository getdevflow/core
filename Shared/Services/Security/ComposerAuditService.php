<?php

declare(strict_types=1);

namespace App\Shared\Services\Security;

use DateTimeImmutable;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

use function App\Shared\Helpers\get_global_option;
use function App\Shared\Helpers\update_global_option;

final class ComposerAuditService
{
    private const string OPTION_RESULT = 'security_audit_result';
    private const string OPTION_EMAIL_ENABLED = 'security_audit_email_enabled';
    private const string OPTION_EMAIL_RECIPIENTS = 'security_audit_email_recipients';
    private const string OPTION_LAST_NOTIFIED_HASH = 'security_audit_last_notified_hash';

    public function __construct(
        private readonly string $basePath,
        private readonly int $timeoutSeconds = 20,
        private readonly bool $auditDevDependencies = false,
    ) {
    }

    /**
     * @return ComposerAuditResult
     * @throws \JsonException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Qubus\Exception\Data\TypeException
     * @throws \ReflectionException
     */
    public function run(): ComposerAuditResult
    {
        try {
            $composerLock = rtrim($this->basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'composer.lock';

            if (!is_file($composerLock)) {
                throw new RuntimeException(
                    'composer.lock was not found. Run composer install before auditing packages.'
                );
            }

            $command = [
                'composer',
                'audit',
                '--format=json',
                '--locked',
                '--no-interaction',
            ];

            if (!$this->auditDevDependencies) {
                $command[] = '--no-dev';
            }

            $process = new Process($command, $this->basePath);
            $process->setTimeout($this->timeoutSeconds);
            $process->run();

            $output = trim($process->getOutput());
            $error = trim($process->getErrorOutput());

            if ($output === '') {
                throw new RuntimeException($error !== '' ? $error : 'Composer audit returned an empty response.');
            }

            $json = json_decode($output, true);

            if (!is_array($json)) {
                throw new RuntimeException('Composer audit returned invalid JSON.');
            }

            $packages = $this->normalizePackages($json);
            $count = $this->countAdvisories($packages);
            $fingerprint = $this->fingerprint($packages);

            $result = new ComposerAuditResult(
                status: $count > 0 ? 'vulnerable' : 'clean',
                checkedAt: new DateTimeImmutable()->format(DATE_ATOM),
                advisoryCount: $count,
                packages: $packages,
                message: $count > 0 ? 'Security advisories were found.' : 'No known security advisories were found.',
                fingerprint: $fingerprint,
                exitCode: $process->getExitCode() ?? 0,
            );

            $this->saveResult($result);
            $this->maybeNotifyAdmins($result);

            return $result;
        } catch (Throwable $e) {
            $result = new ComposerAuditResult(
                status: 'failed',
                checkedAt: new DateTimeImmutable()->format(DATE_ATOM),
                message: $e->getMessage(),
                exitCode: 2,
            );

            $this->saveResult($result);

            return $result;
        }
    }

    /**
     * @return ComposerAuditResult|null
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Qubus\Exception\Data\TypeException
     * @throws \ReflectionException
     */
    public function latest(): ?ComposerAuditResult
    {
        $data = get_global_option(self::OPTION_RESULT, null);

        if (!is_array($data)) {
            return null;
        }

        return ComposerAuditResult::fromArray($data);
    }

    /**
     * @param ComposerAuditResult $result
     * @return void
     * @throws \JsonException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Qubus\Exception\Data\TypeException
     * @throws \ReflectionException
     */
    private function saveResult(ComposerAuditResult $result): void
    {
        update_global_option(self::OPTION_RESULT, $result->toArray());
    }

    private function normalizePackages(array $json): array
    {
        $advisories = $json['advisories'] ?? [];

        if (!is_array($advisories)) {
            return [];
        }

        $packages = [];

        foreach ($advisories as $packageName => $items) {
            if (!is_array($items)) {
                continue;
            }

            $normalized = [];

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $normalized[] = [
                    'advisory_id' => (string) ($item['advisoryId'] ?? $item['advisory_id'] ?? $item['id'] ?? ''),
                    'cve' => (string) ($item['cve'] ?? ''),
                    'title' => (string) ($item['title'] ?? 'Security advisory'),
                    'link' => (string) ($item['link'] ?? ''),
                    'affected_versions' => (string) ($item['affectedVersions'] ?? $item['affected_versions'] ?? ''),
                    'reported_at' => (string) ($item['reportedAt'] ?? $item['reported_at'] ?? ''),
                    'severity' => (string) ($item['severity'] ?? ''),
                ];
            }

            if ($normalized !== []) {
                $packages[$packageName] = $normalized;
            }
        }

        ksort($packages);

        return $packages;
    }

    private function countAdvisories(array $packages): int
    {
        $count = 0;

        foreach ($packages as $items) {
            $count += is_array($items) ? count($items) : 0;
        }

        return $count;
    }

    private function fingerprint(array $packages): string
    {
        $parts = [];

        foreach ($packages as $package => $items) {
            foreach ($items as $item) {
                $parts[] = implode('|', [
                    $package,
                    $item['advisory_id'] ?? '',
                    $item['cve'] ?? '',
                    $item['title'] ?? '',
                    $item['affected_versions'] ?? '',
                ]);
            }
        }

        sort($parts);

        return hash('sha256', implode("\n", $parts));
    }

    /**
     * @param ComposerAuditResult $result
     * @return void
     * @throws \JsonException
     * @throws \PHPMailer\PHPMailer\Exception
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Qubus\Exception\Data\TypeException
     * @throws \Qubus\Exception\Exception
     * @throws \ReflectionException
     */
    private function maybeNotifyAdmins(ComposerAuditResult $result): void
    {
        if (!$result->hasAdvisories()) {
            return;
        }

        $enabled = (bool) get_global_option(self::OPTION_EMAIL_ENABLED, false);

        if (!$enabled) {
            return;
        }

        $lastHash = (string) get_global_option(self::OPTION_LAST_NOTIFIED_HASH, '');

        if ($lastHash === $result->fingerprint) {
            return;
        }

        $recipients = get_global_option(self::OPTION_EMAIL_RECIPIENTS, []);

        if (is_string($recipients)) {
            $recipients = array_filter(array_map('trim', explode(',', $recipients)));
        }

        if (!is_array($recipients) || $recipients === []) {
            return;
        }

        $subject = '[Devflow] Composer security advisories found';

        $body = $this->buildEmailBody($result);

        foreach ($recipients as $recipient) {
            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            \Codefy\Framework\Helpers\mail($recipient, $subject, $body);
        }

        update_global_option(self::OPTION_LAST_NOTIFIED_HASH, $result->fingerprint);
    }

    private function buildEmailBody(ComposerAuditResult $result): string
    {
        $lines = [];

        $lines[] = 'Composer security advisories were found.';
        $lines[] = '';
        $lines[] = 'Checked at: ' . $result->checkedAt;
        $lines[] = 'Advisory count: ' . $result->advisoryCount;
        $lines[] = '';

        foreach ($result->packages as $package => $items) {
            $lines[] = $package;

            foreach ($items as $item) {
                $title = $item['title'] ?: 'Security advisory';
                $cve = $item['cve'] ? ' (' . $item['cve'] . ')' : '';
                $severity = $item['severity'] ? ' [' . $item['severity'] . ']' : '';

                $lines[] = '- ' . $title . $cve . $severity;

                if (!empty($item['affected_versions'])) {
                    $lines[] = '  Affected versions: ' . $item['affected_versions'];
                }

                if (!empty($item['link'])) {
                    $lines[] = '  Link: ' . $item['link'];
                }
            }

            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
