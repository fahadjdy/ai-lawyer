<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\LegalLibrary;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Legal Notebook — a read-only quick-reference of major Indian statutes and
 * their key sections. Available to every authenticated firm member.
 */
class LegalNotebookController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('legal/Notebook', [
            'acts' => LegalLibrary::catalogue(),
        ]);
    }
}
