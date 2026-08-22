<?php

declare(strict_types=1);

use App\Shared\Services\DateTime;

it('honors constructor timezone and locale', function (): void {
    $date = new DateTime('2024-01-15 12:00:00', 'America/Los_Angeles', 'fr');

    expect($date->format('Y-m-d H:i:s P'))->toBe('2024-01-15 12:00:00 -08:00')
        ->and($date->locale()->locale())->toBe('fr');
});

it('updates the wrapped immutable date when changing timezone', function (): void {
    $date = new DateTime('2024-01-15 12:00:00', 'America/Los_Angeles');

    $returned = $date->setTimezone('UTC');

    expect($returned)->not->toBeFalse()
        ->and($date->format('Y-m-d H:i:s P'))->toBe('2024-01-15 20:00:00 +00:00');
});

it('provides exact duration constants', function (): void {
    expect(DateTime::minuteInSeconds())->toBe(60)
        ->and(DateTime::hourInSeconds())->toBe(3600)
        ->and(DateTime::dayInSeconds())->toBe(86400)
        ->and(DateTime::weekInSeconds())->toBe(604800);
});
