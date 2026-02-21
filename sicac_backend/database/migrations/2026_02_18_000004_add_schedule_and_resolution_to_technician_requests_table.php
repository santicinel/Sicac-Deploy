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
            $table->date('scheduled_visit_date')->nullable()->after('time_shift');
            $table->text('resolution_summary')->nullable()->after('scheduled_visit_date');
            $table->timestamp('completed_at')->nullable()->after('resolution_summary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('technician_requests', function (Blueprint $table) {
            $table->dropColumn([
                'scheduled_visit_date',
                'resolution_summary',
                'completed_at',
            ]);
        });
    }
};
