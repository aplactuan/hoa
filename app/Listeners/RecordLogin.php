<?php

namespace App\Listeners;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Login;

class RecordLogin
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->auditLogger->log(
            AuditAction::Login,
            $event->user,
            summary: 'Signed in',
        );
    }
}
