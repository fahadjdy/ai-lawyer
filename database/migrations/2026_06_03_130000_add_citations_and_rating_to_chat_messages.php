<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            // Grounding sources surfaced for an assistant reply (statute sections,
            // cases) — rendered as clickable chips under the message.
            $table->json('citations')->nullable()->after('content');
            // Optional reader feedback on an assistant reply: 1 (up) or -1 (down).
            $table->tinyInteger('rating')->nullable()->after('citations');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn(['citations', 'rating']);
        });
    }
};
