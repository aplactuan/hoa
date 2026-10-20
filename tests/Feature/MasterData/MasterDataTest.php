<?php

use App\Enums\RoleName;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Section;
use App\Models\Street;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Livewire\Livewire;

test('an administrator can add a lookup and the create is audited', function () {
    $this->seed(AccessSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Administrator);

    Livewire::actingAs($admin)
        ->test('pages::master-data')
        ->set('name', 'Palm Court')
        ->call('save')
        ->assertHasNoErrors();

    $section = Section::query()->where('name', 'Palm Court')->first();

    expect($section)->not->toBeNull();
    expect(
        AuditLog::query()
            ->where('auditable_type', Section::class)
            ->where('auditable_id', $section->id)
            ->where('action', 'create')
            ->exists()
    )->toBeTrue();
});

test('a lookup name is required', function () {
    $this->seed(AccessSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Administrator);

    Livewire::actingAs($admin)
        ->test('pages::master-data')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors('name')
        ->assertSee('The name field is required.');
});

test('a sort column outside the allow list is ignored', function () {
    $this->seed(AccessSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Administrator);

    Livewire::actingAs($admin)
        ->test('pages::master-data')
        ->call('sort', 'name; drop table users')
        ->assertSet('sortBy', 'name')
        ->assertSet('sortDirection', 'asc');
});

test('an attachment can be stored against a lookup record', function () {
    $street = Street::factory()->create();

    $attachment = $street->attachments()->create([
        'disk' => 'local',
        'path' => 'lookups/map.pdf',
        'original_name' => 'map.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1200,
    ]);

    expect($attachment)->toBeInstanceOf(Attachment::class)
        ->and($attachment->attachable)->toBeInstanceOf(Street::class)
        ->and($street->attachments)->toHaveCount(1);
});
