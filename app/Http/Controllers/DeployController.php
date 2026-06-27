<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Browser-triggered deploy helper for FTP-only shared hosting (no SSH/artisan).
 * Runs pending migrations and clears the optimization caches, returning the raw
 * console output as plain text. Admin-only (gated by settings.manage in routes).
 */
class DeployController extends Controller
{
    public function migrate(Request $request): Response
    {
        $log = [];

        try {
            Artisan::call('migrate', ['--force' => true]);
            $log[] = "\$ php artisan migrate --force\n".trim(Artisan::output());

            Artisan::call('optimize:clear');
            $log[] = "\$ php artisan optimize:clear\n".trim(Artisan::output());

            $status = 200;
            $log[] = 'DONE ✓';
        } catch (Throwable $e) {
            $status = 500;
            $log[] = 'FAILED ✗ '.$e->getMessage();
        }

        return response(implode("\n\n", $log), $status)
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
