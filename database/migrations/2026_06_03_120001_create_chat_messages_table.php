<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chat_session_id')->constrained('chat_sessions')->cascadeOnDelete();
            // 'user' (the firm member) or 'assistant' (the AI reply).
            $table->string('role', 16);
            $table->text('content');
            $table->timestamps();

            $table->index(['chat_session_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
