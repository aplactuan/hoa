<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
        });

        DB::table('owners')->select('id', 'name')->orderBy('id')->chunkById(500, function ($owners): void {
            foreach ($owners as $owner) {
                DB::table('owners')->where('id', $owner->id)->update([
                    'first_name' => $owner->name,
                ]);
            }
        });

        Schema::table('owners', function (Blueprint $table) {
            $table->string('first_name')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
