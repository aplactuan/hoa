<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot the fine rule on each statement so later setting changes never rewrite old fines.
     */
    public function up(): void
    {
        Schema::table('dues_statements', function (Blueprint $table) {
            $table->string('penalty_type')->nullable()->after('penalty');
            $table->decimal('penalty_rate', 12, 2)->nullable()->after('penalty_type');
            $table->unsignedSmallInteger('grace_days')->nullable()->after('penalty_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dues_statements', function (Blueprint $table) {
            $table->dropColumn(['penalty_type', 'penalty_rate', 'grace_days']);
        });
    }
};
