<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('session_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->enum('role', ['host', 'guest']);
            $table->timestamp('lock_confirmed_at')->nullable();
            $table->timestamp('end_confirmed_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->json('last_integrity_state')->nullable();
            $table->boolean('protection_active')->default(false);
            $table->timestamps();

            $table->unique(['session_id', 'user_id']);
            $table->index('last_heartbeat_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_participants');
    }
};
