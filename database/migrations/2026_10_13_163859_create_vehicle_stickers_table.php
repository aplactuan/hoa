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
        Schema::create('vehicle_stickers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->string('sticker_no')->unique();
            $table->unsignedSmallInteger('year');
            $table->date('issued_on');
            $table->date('expires_on');
            $table->string('status')->default('Active');
            $table->foreignId('charge_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['vehicle_id', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_stickers');
    }
};
