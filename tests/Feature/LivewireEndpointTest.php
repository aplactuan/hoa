<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

test('Livewire uses a stable production update endpoint', function () {
    $route = Route::getRoutes()->getByName('livewire.update');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('livewire/update')
        ->and($route->methods())->toContain('POST')
        ->and($route->middleware())->toContain('web');
});

test('legacy hashed Livewire update paths still resolve', function () {
    $route = Route::getRoutes()->match(Request::create('/livewire-91d7fbb/update', 'POST'));

    expect($route->uri())->toBe('livewire-{hash}/update');
});
