<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Global statute/act reference library shared by every firm.
        Schema::create('legal_sections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('act_name')->index();
            $table->string('section_number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->timestamps();

            $table->index(['act_name', 'section_number']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_sections');
    }
};
