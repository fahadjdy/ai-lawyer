<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\LegalCase;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CaseCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly LegalCase $case) {}
}
