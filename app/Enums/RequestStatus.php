<?php

namespace App\Enums;

enum RequestStatus: string
{
    case PendingReview = 'pending_review';
    case NeedsRevision = 'needs_revision';
    case Rejected = 'rejected';
    case Processing = 'processing';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::PendingReview => 'Menunggu Validasi Admin',
            self::NeedsRevision => 'Perlu Revisi',
            self::Rejected => 'Ditolak',
            self::Processing => 'Diproses Finance',
            self::Done => 'Selesai',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PendingReview => 'yellow',
            self::NeedsRevision => 'orange',
            self::Rejected => 'red',
            self::Processing => 'blue',
            self::Done => 'green',
        };
    }
}
