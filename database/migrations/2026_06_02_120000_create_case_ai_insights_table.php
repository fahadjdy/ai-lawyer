<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_ai_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            // Which assistant produced this: 'analysis' (summary + IPC) or 'cross_exam'.
            $table->string('kind');
            // The normalised AI result, served back as-is so it never regenerates needlessly.
            $table->json('payload');
            // Hash of the case facts + tracking timeline at generation time. When the
            // current case no longer matches, the stored result is flagged stale.
            $table->string('signature', 64);
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // At most one stored result per case per assistant kind.
            $table->unique(['case_id', 'kind']);
            $table->index(['team_id', 'case_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_ai_insights');
    }
};
