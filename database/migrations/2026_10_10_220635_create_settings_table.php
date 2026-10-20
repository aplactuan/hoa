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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('dues_rate_per_sqm', 12, 2)->default(0);
            $table->decimal('penalty_percent', 5, 2)->default(0);
            $table->unsignedTinyInteger('due_day')->default(1);
            $table->unsignedSmallInteger('grace_days')->default(0);
            $table->unsignedSmallInteger('expiry_warning_days')->default(45);
            $table->string('hoa_name');
            $table->text('hoa_address');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
