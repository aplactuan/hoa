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
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('charge_type_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('source');
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->date('due_on')->nullable();
            $table->string('status')->default('Unpaid');
            $table->string('waiver_reason')->nullable();
            $table->foreignId('waiver_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('waived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('charges');
    }
};
