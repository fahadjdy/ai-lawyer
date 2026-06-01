<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CaseStage;
use App\Models\Concerns\BelongsToTeam;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single tracking entry on a case's timeline — a stage update that records
 * what happened and the legal sections applicable at that point. Comparing
 * consecutive entries shows how the sections evolved (e.g. after investigation).
 */
class CaseEvent extends Model
{
    use BelongsToTeam, HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'team_id',
        'case_id',
        'stage',
        'title',
        'description',
        'sections',
        'occurred_on',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'stage' => CaseStage::class,
            'sections' => 'array',
            'occurred_on' => 'date',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
