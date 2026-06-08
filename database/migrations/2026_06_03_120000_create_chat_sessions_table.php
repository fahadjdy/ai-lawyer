<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            // The firm member who owns this conversation — chats are private to them.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Optional case the conversation is anchored to; its facts + tracking
            // history are fed to the assistant as context when present.
            $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();
            $table->string('title')->default('New chat');
            // Stamped on each new message so the session list can sort by recency.
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'user_id', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_sessions');
    }
};
