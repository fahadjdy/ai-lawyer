<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Email verification is now enforced (User implements MustVerifyEmail + the
 * `verified` middleware). Existing accounts predate that rule, so mark them
 * verified to avoid locking current members out. New sign-ups must verify.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // No-op: we cannot reliably tell which rows were backfilled.
    }
};
