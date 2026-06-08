<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single turn in a {@see ChatSession} — either the firm member's prompt
 * ('user') or the AI assistant's reply ('assistant').
 */
class ChatMessage extends Model
{
    use BelongsToTeam, HasFactory;

    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    protected $fillable = [
        'team_id',
        'chat_session_id',
        'role',
        'content',
        'citations',
        'rating',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'team_id' => 'integer',
            'chat_session_id' => 'integer',
            'citations' => 'array',
            'rating' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }
}
