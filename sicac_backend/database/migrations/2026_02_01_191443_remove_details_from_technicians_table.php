<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite cannot rebuild the table if the unique index still references a dropped column.
            try {
                Schema::table('technicians', function (Blueprint $table) {
                    $table->dropUnique('technicians_dni_unique');
                });
            } catch (\Throwable $exception) {
                // Ignore if the index does not exist.
            }
        }

        Schema::table('technicians', function (Blueprint $table) {
            $table->dropColumn(['dni', 'phone', 'address', 'city']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('technicians', function (Blueprint $table) {
            $table->string('dni')->unique();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
        });
    }
};
