<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cached AI result for a case — the "analysis" (summary + IPC sections) or the
 * "cross_exam" (anticipated opponent/judge questions). Stored so a lawyer who
 * revisits the case sees the suggestions instantly instead of paying to
 * regenerate. The {@see $signature} captures the case state at generation time,
 * so the UI can prompt a regenerate once the case — notably its tracking
 * timeline — has moved on.
 */
class CaseAiInsight extends Model
{
    use BelongsToTeam, HasFactory;

    public const KIND_ANALYSIS = 'analysis';

    public const KIND_CROSS_EXAM = 'cross_exam';

    protected $fillable = [
        'team_id',
        'case_id',
        'kind',
        'payload',
        'signature',
        'generated_by',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Persist (or refresh) the stored result for a case + kind, stamping the
     * current case signature so staleness can be detected later.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function store(LegalCase $case, string $kind, array $payload, ?User $user): self
    {
        return static::updateOrCreate(
            ['case_id' => $case->id, 'kind' => $kind],
            [
                'team_id' => $case->team_id,
                'payload' => $payload,
                'signature' => static::signatureFor($case),
                'generated_by' => $user?->id,
            ],
        );
    }

    /**
     * Deterministic fingerprint of everything the assistants reason from — the
     * case facts plus its tracking timeline. Identical inputs hash identically,
     * so a changed hash means the case has moved on since a result was stored.
     */
    public static function signatureFor(LegalCase $case): string
    {
        $material = [
            'title' => $case->title,
            'description' => $case->description,
            'case_type' => $case->case_type?->value,
            'opposing_party' => $case->opposing_party,
            'court_name' => $case->court_name,
            'court_type' => $case->court_type,
            'judge_name' => $case->judge_name,
            'history' => $case->trackingHistory(),
        ];

        return hash('sha256', (string) json_encode($material));
    }
}
