<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('total_success_seconds')->default(0);
            $table->unsignedBigInteger('best_session_seconds')->default(0);
            $table->unsignedInteger('current_streak_count')->default(0);
            $table->unsignedInteger('longest_streak_count')->default(0);
            $table->unsignedInteger('failed_session_count')->default(0);
            $table->unsignedInteger('successful_session_count')->default(0);
            $table->timestamp('last_success_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_stats');
    }
};
