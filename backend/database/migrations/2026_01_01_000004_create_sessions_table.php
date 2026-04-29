<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * App-domain "sessions" table for offline mutual sessions. The framework's
 * session driver is configured to "array" so this name does not collide.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->enum('state', [
                'created',
                'pending_second_user',
                'ready_to_lock',
                'active',
                'success',
                'failed',
                'cancelled',
            ])->default('created');
            $table->foreignId('host_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->foreignId('failed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('end_requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['state', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
