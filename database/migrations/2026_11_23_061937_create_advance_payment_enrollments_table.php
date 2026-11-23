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
        Schema::create('advance_payment_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advance_payment_promo_id')->constrained()->restrictOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->decimal('monthly_fee', 12, 2);
            $table->decimal('discount_total', 12, 2);
            $table->unique(['advance_payment_promo_id', 'property_id'], 'advance_payment_enrollment_unique');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advance_payment_enrollments');
    }
};
