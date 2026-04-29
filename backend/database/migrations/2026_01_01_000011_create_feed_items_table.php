<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('feed_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('sessions')->nullOnDelete();
            $table->enum('type', [
                'session_success',
                'session_failed',
                'streak_milestone',
                'friend_joined',
            ]);
            $table->enum('visibility', ['friends', 'private'])->default('friends');
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_items');
    }
};
