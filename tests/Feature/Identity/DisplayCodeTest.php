<?php

use App\Services\DisplayCode;
use Illuminate\Support\Facades\DB;

test('property owner statement and receipt codes follow the display formats', function () {
    $codes = app(DisplayCode::class);

    expect($codes->next('property'))->toBe('PRP-0001')
        ->and($codes->next('property'))->toBe('PRP-0002')
        ->and($codes->next('owner'))->toBe('OWN-0001')
        ->and($codes->next('statement', '202610'))->toBe('SOA-202610-001')
        ->and($codes->next('statement', '202610'))->toBe('SOA-202610-002')
        ->and($codes->next('statement', '202611'))->toBe('SOA-202611-001')
        ->and($codes->next('official_receipt'))->toBe('OR-000001');
});

test('two simultaneous saves never produce the same display id', function () {
    $database = storage_path('framework/sequence-race.sqlite');

    if (file_exists($database)) {
        unlink($database);
    }

    touch($database);

    $sqlite = config('database.connections.sqlite');
    expect($sqlite)->toBeArray();

    config()->set('database.connections.sequence_race', array_merge($sqlite, [
        'database' => $database,
    ]));

    DB::purge('sequence_race');

    $this->artisan('migrate', [
        '--database' => 'sequence_race',
        '--force' => true,
    ])->assertSuccessful();

    DB::connection('sequence_race')->statement('PRAGMA journal_mode=WAL');
    DB::connection('sequence_race')->statement('PRAGMA busy_timeout = 8000');
    DB::disconnect('sequence_race');

    $descriptor = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $processes = [];

    foreach ([0, 1] as $ignored) {
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, base_path('tests/Support/next-display-code.php'), $database],
            $descriptor,
            $pipes,
            base_path(),
        );

        expect($process)->not->toBeFalse();
        $processes[] = ['process' => $process, 'pipes' => $pipes];
    }

    $codes = [];

    foreach ($processes as $entry) {
        $stdout = stream_get_contents($entry['pipes'][1]);
        $stderr = stream_get_contents($entry['pipes'][2]);
        fclose($entry['pipes'][0]);
        fclose($entry['pipes'][1]);
        fclose($entry['pipes'][2]);
        $exit = proc_close($entry['process']);

        expect($exit)->toBe(0, $stderr ?: $stdout);
        $codes[] = trim((string) $stdout);
    }

    expect($codes)->toHaveCount(2)
        ->and(array_unique($codes))->toHaveCount(2)
        ->and($codes)->each->toMatch('/^PRP-\d{4}$/');

    if (file_exists($database)) {
        unlink($database);
    }
});
