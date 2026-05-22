<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
            $table->foreignId('team_id')->nullable()->after('uuid')->constrained('teams')->nullOnDelete();
            $table->string('phone')->nullable()->after('email');
            $table->string('designation')->nullable()->after('phone');
            $table->string('avatar_path')->nullable()->after('designation');
            $table->boolean('is_active')->default(true)->after('avatar_path');
            $table->timestamp('last_login_at')->nullable()->after('is_active');

            $table->index(['team_id', 'is_active']);
        });

        // Backfill UUIDs for any pre-existing rows, then enforce uniqueness.
        foreach (DB::table('users')->whereNull('uuid')->pluck('id') as $id) {
            DB::table('users')->where('id', $id)->update(['uuid' => (string) Str::uuid()]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
            $table->dropUnique(['uuid']);
            $table->dropColumn(['uuid', 'phone', 'designation', 'avatar_path', 'is_active', 'last_login_at']);
        });
    }
};
