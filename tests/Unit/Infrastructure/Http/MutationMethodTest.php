<?php

declare(strict_types=1);

use App\Infrastructure\Http\Controllers\AdminAuthController;
use App\Infrastructure\Http\Controllers\WebsiteManagerController;
use App\Infrastructure\Services\Vihzhuo\PageEditor;
use App\Infrastructure\Services\Vihzhuo\WebsiteManager;
use Qubus\Http\ServerRequest;
use Qubus\Routing\Router;

it('rejects GET requests before any logout or page deletion effects', function (): void {
    $request = new ServerRequest()->withMethod('GET');
    $auth = new AdminAuthController(new ReflectionClass(Router::class)->newInstanceWithoutConstructor());
    foreach ([$auth->logout($request), new WebsiteManagerController()->destroy($request, 1),
        new WebsiteManager()->handleRequest($request, action: 'destroy'),
        new \App\Infrastructure\Services\Vihzhuo\VihzhuoAuth()->handleRequest($request, 'logout'),
        new \App\Infrastructure\Services\Vihzhuo\VihzhuoAuth()->handleRequest($request, 'logout')] as $response) {
        expect($response->getStatusCode())->toBe(405)->and($response->getHeaderLine('Allow'))->toBe('POST');
    }
});

it('rejects non-POST editor saves and asset mutations', function (string $action): void {
    $response = new ReflectionClass(PageEditor::class)->newInstanceWithoutConstructor()->handleRequest(new ServerRequest()->withMethod('GET'), action: $action);
    expect($response->getStatusCode())->toBe(405)->and($response->getHeaderLine('Allow'))->toBe('POST');
})->with(['store', 'upload', 'upload_delete']);
