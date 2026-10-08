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

    public function shortLabel(): string
    {
        return match ($this) {
            self::PendingReview => 'Menunggu Validasi',
            default => $this->label(),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PendingReview => 'clock',
            self::NeedsRevision => 'arrow-path',
            self::Rejected => 'x-circle',
            self::Processing => 'banknotes',
            self::Done => 'check-circle',
        };
    }

    /**
     * Tailwind classes for badges. Kept as full literals so the
     * Tailwind scanner picks them up from this file.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::PendingReview => 'bg-amber-50 text-amber-700 ring-amber-600/20',
            self::NeedsRevision => 'bg-orange-50 text-orange-700 ring-orange-600/20',
            self::Rejected => 'bg-rose-50 text-rose-700 ring-rose-600/20',
            self::Processing => 'bg-sky-50 text-sky-700 ring-sky-600/20',
            self::Done => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        };
    }

    public function dotClasses(): string
    {
        return match ($this) {
            self::PendingReview => 'bg-amber-500',
            self::NeedsRevision => 'bg-orange-500',
            self::Rejected => 'bg-rose-500',
            self::Processing => 'bg-sky-500',
            self::Done => 'bg-emerald-500',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Rejected, self::Done], true);
    }
}
