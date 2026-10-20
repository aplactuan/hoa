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
        Schema::create('owners', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('mailing_address')->nullable();
            $table->string('civil_status')->nullable();
            $table->string('ownership_type')->default('Sole Owner');
            $table->string('tct_no')->nullable();
            $table->string('tax_declaration_no')->nullable();
            $table->string('deed_reference')->nullable();
            $table->date('acquired_on')->nullable();
            $table->boolean('is_registered')->default(false);
            $table->date('verified_on')->nullable();
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->string('status')->default('Current');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['property_id', 'status']);
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->foreignId('current_owner_id')->nullable()->constrained('owners')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_owner_id');
        });
        Schema::dropIfExists('owners');
    }
};
