<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTeam;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    use BelongsToTeam, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'team_id',
        'case_id',
        'client_id',
        'folder_id',
        'parent_id',
        'version',
        'is_latest',
        'name',
        'original_name',
        'disk',
        'path',
        'mime_type',
        'extension',
        'size',
        'hash',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'team_id' => 'integer',
            'case_id' => 'integer',
            'client_id' => 'integer',
            'folder_id' => 'integer',
            'parent_id' => 'integer',
            'uploaded_by' => 'integer',
            'is_latest' => 'boolean',
            'version' => 'integer',
            'size' => 'integer',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(DocumentFolder::class, 'folder_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderByDesc('version');
    }

    public function scopeLatestVersions(Builder $query): Builder
    {
        return $query->where('is_latest', true);
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;

        return round($bytes / (1024 ** $i), 2).' '.$units[$i];
    }

    /**
     * Short-lived, signed temporary URL for secure downloads (cloud disks).
     */
    public function temporaryUrl(int $minutes = 5): ?string
    {
        $disk = Storage::disk($this->disk);

        return method_exists($disk, 'temporaryUrl')
            ? $disk->temporaryUrl($this->path, now()->addMinutes($minutes))
            : null;
    }
}
