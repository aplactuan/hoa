<?php

declare(strict_types=1);
use App\Services\DisplayCode;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;

$database = $argv[1] ?? '';

if ($database === '') {
    fwrite(STDERR, "Missing database path.\n");
    exit(1);
}

putenv('APP_ENV=testing');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE='.$database);
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = $database;
$_SERVER['APP_ENV'] = 'testing';
$_SERVER['DB_CONNECTION'] = 'sqlite';
$_SERVER['DB_DATABASE'] = $database;

require __DIR__.'/../../vendor/autoload.php';

/** @var Application $app */
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config([
    'database.default' => 'sqlite',
    'database.connections.sqlite.database' => $database,
]);

DB::purge('sqlite');
DB::reconnect('sqlite');
DB::statement('PRAGMA busy_timeout = 8000');

echo app(DisplayCode::class)->next('property');
