<?php

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\Facility;
use App\Models\Setting;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\ViolationType;
use App\Support\Money;
use Database\Seeders\AccessSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Support\Facades\Route;

test('money format hides amounts without the financial permission', function () {
    $this->seed(AccessSeeder::class);

    $guard = User::factory()->create();
    $guard->assignRole(RoleName::SecurityGuard);
    $this->actingAs($guard);

    expect(Money::format('98765.43'))->toBeNull();

    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleName::Treasurer);
    $this->actingAs($treasurer);

    expect(Money::format('98765.43'))->toBe('₱98,765.43');
    expect($treasurer->hasPermission(Permission::FinancialView))->toBeTrue();
    expect($guard->hasPermission(Permission::FinancialView))->toBeFalse();
});

test('a security guard response never contains a money value', function () {
    $this->seed(AccessSeeder::class);
    $this->seed(MasterDataSeeder::class);

    Setting::query()->update(['dues_rate_per_sqm' => '98765.43']);
    Facility::query()->update(['fee' => '98765.43', 'deposit' => '98765.43']);
    TransactionType::query()->update(['default_fee' => '98765.43']);
    ViolationType::query()->update(['default_fine' => '98765.43']);

    $guard = User::factory()->create();
    $guard->assignRole(RoleName::SecurityGuard);

    $routes = collect(Route::getRoutes())->filter(function ($route): bool {
        return in_array('GET', $route->methods(), true)
            && ! str_contains($route->uri(), '{')
            && ! str_starts_with($route->uri(), '_')
            && ! str_starts_with($route->uri(), 'livewire')
            && ! str_starts_with($route->uri(), 'flux');
    });

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        $response = $this->actingAs($guard)->get('/'.ltrim($route->uri(), '/'));

        expect($response->status())->toBeIn([200, 302, 401, 403, 404, 405], $route->uri());

        $content = $response->getContent() ?: '';

        expect($content)
            ->not->toContain('98765.43')
            ->and($content)->not->toContain('98,765.43')
            ->and($content)->not->toContain('₱');
    }
});
