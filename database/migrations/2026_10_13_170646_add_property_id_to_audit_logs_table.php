<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->index(['property_id', 'created_at']);
        });

        $this->restoreSqliteProtection();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('property_id');
        });

        $this->restoreSqliteProtection();
    }

    private function restoreSqliteProtection(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        DB::unprepared("CREATE TRIGGER IF NOT EXISTS audit_logs_prevent_update BEFORE UPDATE ON audit_logs BEGIN SELECT RAISE(ABORT, 'audit_logs are immutable'); END");
        DB::unprepared("CREATE TRIGGER IF NOT EXISTS audit_logs_prevent_delete BEFORE DELETE ON audit_logs BEGIN SELECT RAISE(ABORT, 'audit_logs are immutable'); END");
    }
};
