<?php

use Illuminate\Support\Facades\Route;

test('Livewire uses a stable production update endpoint', function () {
    $route = Route::getRoutes()->getByName('livewire.update');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('livewire/update')
        ->and($route->methods())->toContain('POST')
        ->and($route->middleware())->toContain('web');
});
