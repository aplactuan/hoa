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
        Schema::create('violations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('violation_type_id')->constrained()->restrictOnDelete();
            $table->text('description');
            $table->date('noticed_on')->nullable();
            $table->date('deadline_on')->nullable();
            $table->decimal('fine', 12, 2)->default(0);
            $table->string('status')->default('Open');
            $table->text('resolution')->nullable();
            $table->date('resolved_on')->nullable();
            $table->foreignId('charge_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['status', 'deadline_on']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('violations');
    }
};
