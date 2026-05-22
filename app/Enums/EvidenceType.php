<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumHelpers;

enum EvidenceType: string
{
    use HasEnumHelpers;

    case Document = 'document';
    case Physical = 'physical';
    case Photo = 'photo';
    case Video = 'video';
    case Audio = 'audio';
    case Testimony = 'testimony';
    case Digital = 'digital';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return 'slate';
    }
}
