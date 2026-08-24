<?php

declare(strict_types=1);

use App\Shared\Services\Image;

it('calculates bounded image dimensions', function (): void {
    expect(Image::resize(1600, 900, 400))->toBe('width="400" height="225"')
        ->and(Image::resize(600, 1200, 300))->toBe('width="150" height="300"');
});

it('rejects invalid image dimensions', function (int $width, int $height, int $target): void {
    Image::resize($width, $height, $target);
})->with([
    [0, 100, 50],
    [100, 0, 50],
    [100, 100, 0],
    [-1, 100, 50],
])->throws(InvalidArgumentException::class);
