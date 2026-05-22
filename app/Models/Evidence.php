<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EvidenceStatus;
use App\Enums\EvidenceType;
use App\Models\Concerns\BelongsToTeam;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Evidence extends Model
{
    use BelongsToTeam, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'evidence';

    protected $fillable = [
        'uuid',
        'team_id',
        'case_id',
        'document_id',
        'reference_number',
        'title',
        'description',
        'type',
        'status',
        'collected_at',
        'collected_by',
        'chain_of_custody',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => EvidenceType::class,
            'status' => EvidenceStatus::class,
            'collected_at' => 'datetime',
            'chain_of_custody' => 'array',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
