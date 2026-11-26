<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->decimal('membership_fee', 12, 2)->default(0)->after('penalty_percent');
            $table->string('penalty_type')->default('percent')->after('membership_fee');
            $table->decimal('penalty_fixed_amount', 12, 2)->default(0)->after('penalty_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['membership_fee', 'penalty_type', 'penalty_fixed_amount']);
        });
    }
};
