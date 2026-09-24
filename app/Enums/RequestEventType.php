<?php

namespace App\Enums;

enum RequestEventType: string
{
    case Submitted = 'submitted';
    case NeedsRevision = 'needs_revision';
    case Rejected = 'rejected';
    case Approved = 'approved';
    case Paid = 'paid';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Pengajuan dibuat',
            self::NeedsRevision => 'Diminta revisi',
            self::Rejected => 'Ditolak',
            self::Approved => 'Disetujui Admin',
            self::Paid => 'Pencairan diproses',
            self::Completed => 'Selesai',
        };
    }
}
