<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\FileResource;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;

it('round trips roles and permissions through the configured filesystem', function (): void {
    $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
    $resource = new FileResource('rbac.json', $filesystem);
    $permission = $resource->addPermission('content.edit', 'Edit content');
    $role = $resource->addRole('editor', 'Editor');
    $role->addPermission($permission);

    $resource->save();

    $restored = new FileResource('rbac.json', $filesystem);
    $restored->load();

    expect($restored->getPermission('content.edit'))->not->toBeNull()
        ->and($restored->getRole('editor'))->not->toBeNull()
        ->and($restored->getRole('editor')->getPermissions())->toHaveCount(1);
});

it('loads a missing RBAC file as an empty resource', function (): void {
    $resource = new FileResource(
        'missing.json',
        new Filesystem(new InMemoryFilesystemAdapter())
    );

    $resource->load();

    expect($resource->roles)->toBe([])
        ->and($resource->permissions)->toBe([]);
});
