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

    public function icon(): string
    {
        return match ($this) {
            self::Submitted => 'document-plus',
            self::NeedsRevision => 'arrow-path',
            self::Rejected => 'x-circle',
            self::Approved => 'check',
            self::Paid => 'banknotes',
            self::Completed => 'check-circle',
        };
    }

    public function iconClasses(): string
    {
        return match ($this) {
            self::Submitted => 'bg-brand-50 text-brand-600 ring-brand-100',
            self::NeedsRevision => 'bg-orange-50 text-orange-600 ring-orange-100',
            self::Rejected => 'bg-rose-50 text-rose-600 ring-rose-100',
            self::Approved => 'bg-sky-50 text-sky-600 ring-sky-100',
            self::Paid, self::Completed => 'bg-emerald-50 text-emerald-600 ring-emerald-100',
        };
    }
}
