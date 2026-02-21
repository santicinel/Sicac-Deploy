<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('technician_requests', function (Blueprint $table) {
            $table->foreignId('repaired_product_id')
                ->nullable()
                ->after('claim_id')
                ->constrained('products')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('technician_requests', function (Blueprint $table) {
            $table->dropForeign(['repaired_product_id']);
            $table->dropColumn('repaired_product_id');
        });
    }
};
