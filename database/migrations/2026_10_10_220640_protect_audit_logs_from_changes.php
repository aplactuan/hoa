<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Triggers reject UPDATE and DELETE for every database user. On MySQL the
     * migration also revokes those privileges from the current account when
     * that account is allowed to grant them.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER audit_logs_prevent_update BEFORE UPDATE ON audit_logs BEGIN SELECT RAISE(ABORT, 'audit_logs are immutable'); END");
            DB::unprepared("CREATE TRIGGER audit_logs_prevent_delete BEFORE DELETE ON audit_logs BEGIN SELECT RAISE(ABORT, 'audit_logs are immutable'); END");

            return;
        }

        if ($driver !== 'mysql') {
            return;
        }

        DB::unprepared("CREATE TRIGGER audit_logs_prevent_update BEFORE UPDATE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs are immutable'");
        DB::unprepared("CREATE TRIGGER audit_logs_prevent_delete BEFORE DELETE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs are immutable'");

        $currentUser = DB::selectOne('SELECT CURRENT_USER() AS username');

        if (! is_object($currentUser) || ! isset($currentUser->username) || ! is_string($currentUser->username)) {
            return;
        }

        $parts = explode('@', $currentUser->username, 2);

        if (count($parts) !== 2) {
            return;
        }

        $name = str_replace("'", "''", trim($parts[0], "'"));
        $host = str_replace("'", "''", trim($parts[1], "'"));
        $database = str_replace('`', '``', Schema::getConnection()->getDatabaseName());

        try {
            DB::statement("REVOKE UPDATE, DELETE ON `{$database}`.`audit_logs` FROM '{$name}'@'{$host}'");
        } catch (Throwable) {
            // Triggers still block changes when this account cannot revoke its own grants.
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_prevent_update');
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_prevent_delete');
        }

        if ($driver === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_prevent_update');
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_prevent_delete');
        }
    }
};
