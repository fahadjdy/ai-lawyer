<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTeam;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A conversation with the AI legal assistant. Owned by a single firm member and
 * scoped to their team. May optionally be anchored to a {@see LegalCase}, in
 * which case that case's facts and tracking history are fed to the assistant as
 * context for every reply.
 */
class ChatSession extends Model
{
    use BelongsToTeam, HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'case_id',
        'title',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'team_id' => 'integer',
            'user_id' => 'integer',
            'case_id' => 'integer',
            'last_message_at' => 'datetime',
        ];
    }

    /** @return HasMany<ChatMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('id');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
