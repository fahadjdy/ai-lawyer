<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            // 0–100 assessment of how strongly the matter is in the firm's
            // favour. Nullable means "not yet assessed".
            $table->unsignedTinyInteger('favorability')->nullable()->after('priority');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn('favorability');
        });
    }
};
