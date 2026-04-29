<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique()->nullable();
            $table->string('email')->unique();
            $table->string('phone_e164')->nullable()->index();
            $table->string('phone_hash', 64)->nullable()->unique();
            $table->string('email_hash', 64)->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('share_successes')->default(true);
            $table->boolean('share_failures')->default(false);
            $table->boolean('share_streaks')->default(true);
            $table->boolean('discoverable_by_contacts')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
