<?php

declare(strict_types=1);

use App\Shared\Http\StreamedResponse;

it('streams its callback only once', function (): void {
    $response = new StreamedResponse(static function (): void {
        echo 'streamed';
    });

    ob_start();
    $response->sendContent();
    $response->sendContent();
    $output = ob_get_clean();

    expect($output)->toBe('streamed');
});

it('keeps the current status when headers are sent without an override', function (): void {
    $response = new StreamedResponse(static function (): void {
    }, 206);

    expect($response->sendHeaders())->toBe($response)
        ->and($response->getStatusCode())->toBe(206);
});

it('does not consume the stream when null content is assigned', function (): void {
    $response = new StreamedResponse(static function (): void {
        echo 'available';
    });

    $response->setContent();

    ob_start();
    $response->sendContent();
    $output = ob_get_clean();

    expect($output)->toBe('available');
});
