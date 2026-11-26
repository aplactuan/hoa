<?php

use App\Jobs\GenerateMonthlyStatements;
use App\Jobs\RecalculateAccounts;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new GenerateMonthlyStatements)->monthlyOn(1, '01:00')->timezone('Asia/Manila')->onOneServer();
Schedule::job(new RecalculateAccounts)->dailyAt('00:30')->timezone('Asia/Manila')->onOneServer();
