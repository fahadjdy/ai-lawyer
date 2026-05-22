<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('case_number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('case_type')->default('civil');
            $table->string('status')->default('intake');
            $table->string('priority')->default('medium');

            // Court / jurisdiction details
            $table->string('court_name')->nullable();
            $table->string('court_type')->nullable();
            $table->string('jurisdiction')->nullable();
            $table->string('judge_name')->nullable();
            $table->string('opposing_party')->nullable();
            $table->string('opposing_counsel')->nullable();

            $table->date('filing_date')->nullable();
            $table->dateTime('next_hearing_at')->nullable();
            $table->json('tags')->nullable();

            $table->foreignId('lead_lawyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Case numbers are unique within a firm, not globally.
            $table->unique(['team_id', 'case_number']);
            $table->index(['team_id', 'status', 'priority']);
            $table->index(['team_id', 'next_hearing_at']);
            $table->index(['team_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cases');
    }
};
