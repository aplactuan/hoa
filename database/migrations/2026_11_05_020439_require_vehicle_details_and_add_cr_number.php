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
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('cr_number')->nullable();
        });

        foreach (['make', 'model', 'color'] as $column) {
            DB::table('vehicles')->whereNull($column)->orWhere($column, '')->update([$column => 'Unknown']);
        }

        DB::table('vehicles')->select('id')->chunkById(500, function ($vehicles): void {
            foreach ($vehicles as $vehicle) {
                DB::table('vehicles')->where('id', $vehicle->id)->update(['cr_number' => 'LEGACY-CR-'.$vehicle->id]);
            }
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('make')->change();
            $table->string('model')->change();
            $table->string('color')->change();
            $table->string('cr_number')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('make')->nullable()->change();
            $table->string('model')->nullable()->change();
            $table->string('color')->nullable()->change();
            $table->dropColumn('cr_number');
        });
    }
};
