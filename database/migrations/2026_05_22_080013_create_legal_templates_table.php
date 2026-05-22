<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // Null team_id => global template available to every firm.
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->longText('body');
            $table->json('variables')->nullable();
            $table->boolean('is_global')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'category']);
            $table->index('is_global');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_templates');
    }
};
