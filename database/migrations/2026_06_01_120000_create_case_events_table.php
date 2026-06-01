<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->string('stage')->default('complaint');
            $table->string('title');
            $table->text('description')->nullable();
            // Snapshot of the legal sections applicable at this point in the case.
            $table->json('sections')->nullable();
            $table->date('occurred_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['team_id', 'case_id']);
            $table->index(['case_id', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_events');
    }
};
