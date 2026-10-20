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
        Schema::create('facility_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->date('event_on');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('purpose');
            $table->unsignedInteger('guests')->default(0);
            $table->decimal('fee', 12, 2)->default(0);
            $table->decimal('deposit', 12, 2)->default(0);
            $table->string('approval_status')->default('Pending');
            $table->string('payment_status')->default('Unpaid');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('fee_charge_id')->nullable()->constrained('charges')->nullOnDelete();
            $table->foreignId('deposit_charge_id')->nullable()->constrained('charges')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['facility_id', 'event_on', 'approval_status']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facility_reservations');
    }
};
