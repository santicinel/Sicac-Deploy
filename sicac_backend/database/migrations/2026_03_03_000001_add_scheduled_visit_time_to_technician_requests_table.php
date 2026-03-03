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
            $table->time('scheduled_visit_time')->nullable()->after('scheduled_visit_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('technician_requests', function (Blueprint $table) {
            $table->dropColumn('scheduled_visit_time');
        });
    }
};
