<?php

declare(strict_types=1);

namespace App\Shared\Helpers;

use App\Shared\Services\Security\ComposerAuditResult;

/**
 * @return ComposerAuditResult|null
 * @throws \Psr\Container\ContainerExceptionInterface
 * @throws \Psr\Container\NotFoundExceptionInterface
 * @throws \Psr\SimpleCache\InvalidArgumentException
 * @throws \Qubus\Exception\Data\TypeException
 * @throws \ReflectionException
 */
function security_audit_result(): ?ComposerAuditResult
{
    $data = get_global_option('security_audit_result', null);

    if (!is_array($data)) {
        return null;
    }

    return ComposerAuditResult::fromArray($data);
}

/**
 * @return bool
 * @throws \Psr\Container\ContainerExceptionInterface
 * @throws \Psr\Container\NotFoundExceptionInterface
 * @throws \Psr\SimpleCache\InvalidArgumentException
 * @throws \Qubus\Exception\Data\TypeException
 * @throws \ReflectionException
 */
function security_audit_has_advisories(): bool
{
    return security_audit_result()?->hasAdvisories() ?? false;
}

/**
 * @return bool
 * @throws \Psr\Container\ContainerExceptionInterface
 * @throws \Psr\Container\NotFoundExceptionInterface
 * @throws \Psr\SimpleCache\InvalidArgumentException
 * @throws \Qubus\Exception\Data\TypeException
 * @throws \ReflectionException
 */
function security_audit_failed(): bool
{
    return security_audit_result()?->failed() ?? false;
}
