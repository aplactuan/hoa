<?php

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Owner;
use App\Models\Property;

test('moves current owners who started later back to the given date and audits the change', function () {
    $owner = Owner::factory()->create(['started_on' => '2026-10-27']);
    $alreadyEarlier = Owner::factory()->create(['started_on' => '2024-06-01']);
    $former = Owner::factory()->create(['started_on' => '2026-03-01', 'ended_on' => '2026-05-01', 'status' => 'Former']);

    $this->artisan('owners:backdate-start', ['--date' => '2025-01-01'])
        ->expectsOutputToContain("{$owner->property->code} · {$owner->name}: 2026-10-27 to 2025-01-01")
        ->expectsOutputToContain('Moved 1 owners; skipped 0.')
        ->assertSuccessful();

    expect($owner->fresh()->started_on->toDateString())->toBe('2025-01-01')
        ->and($alreadyEarlier->fresh()->started_on->toDateString())->toBe('2024-06-01')
        ->and($former->fresh()->started_on->toDateString())->toBe('2026-03-01')
        ->and(AuditLog::query()->where('auditable_type', $owner->getMorphClass())->where('auditable_id', $owner->id)->where('action', AuditAction::Update)->sole()->new_values)->toMatchArray(['started_on' => '2025-01-01 00:00:00']);
});

test('skips a property whose previous owner held it after the given date', function () {
    $property = Property::factory()->create();
    Owner::factory()->for($property)->create(['started_on' => '2020-01-01', 'ended_on' => '2025-06-30', 'status' => 'Former']);
    $current = Owner::factory()->for($property)->create(['started_on' => '2025-06-30']);

    $this->artisan('owners:backdate-start', ['--date' => '2025-01-01'])
        ->expectsOutputToContain('Moved 0 owners; skipped 1.')
        ->assertSuccessful();

    expect($current->fresh()->started_on->toDateString())->toBe('2025-06-30');
});

test('a dry run shows the change without saving it', function () {
    $owner = Owner::factory()->create(['started_on' => '2026-10-27']);

    $this->artisan('owners:backdate-start', ['--date' => '2025-01-01', '--dry-run' => true])
        ->expectsOutputToContain('Would move 1 owners; skipped 0.')
        ->assertSuccessful();

    expect($owner->fresh()->started_on->toDateString())->toBe('2026-10-27');
});

test('rejects a date that is not a calendar date', function () {
    $this->artisan('owners:backdate-start', ['--date' => '2025-13-01'])->assertFailed();
});
