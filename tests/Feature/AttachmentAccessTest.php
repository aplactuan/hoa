<?php

use App\Enums\RoleName;
use App\Models\Property;
use App\Models\User;
use App\Services\PropertyRegistry;
use Database\Seeders\AccessSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('an office user can attach a private owner document and guards cannot download it', function () {
    Storage::fake('local');
    $this->seed(AccessSeeder::class);
    $property = Property::factory()->create();
    app(PropertyRegistry::class)->transferOwnership($property, [
        'name' => 'Maria Santos',
        'ownership_type' => 'Sole Owner',
        'started_on' => now()->toDateString(),
    ]);
    $staff = User::factory()->create();
    $staff->assignRole(RoleName::OfficeStaff);
    $guard = User::factory()->create();
    $guard->assignRole(RoleName::SecurityGuard);

    Livewire::actingAs($staff)->test('pages::property-profile', ['property' => $property])
        ->set('uploadTarget', 'owner')
        ->set('attachment', UploadedFile::fake()->create('deed.pdf', 100, 'application/pdf'))
        ->call('uploadDocument')
        ->assertHasNoErrors();

    $attachment = $property->fresh()->currentOwner->attachments()->sole();
    Storage::disk('local')->assertExists($attachment->path);
    $this->actingAs($staff)->get(route('attachments.download', $attachment))->assertOk();
    $this->actingAs($guard)->get(route('attachments.download', $attachment))->assertForbidden();
});
