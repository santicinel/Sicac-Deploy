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
        Schema::create('client_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_request_id')->constrained('technician_requests')->cascadeOnDelete();
            $table->foreignId('client_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained('technicians')->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['technician_request_id', 'technician_id'], 'client_ratings_unique_case_technician');
            $table->index(['client_user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_ratings');
    }
};
