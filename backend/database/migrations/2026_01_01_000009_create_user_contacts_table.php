<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contacts_import_id')->constrained('contacts_imports')->cascadeOnDelete();
            $table->string('hash', 64);
            $table->foreignId('matched_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'hash']);
            $table->index('hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_contacts');
    }
};
