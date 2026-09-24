<?php

namespace App\Enums;

enum Role: string
{
    case Requester = 'requester';
    case Admin = 'admin';
    case Finance = 'finance';
    case Head = 'head';

    public function label(): string
    {
        return match ($this) {
            self::Requester => 'Pengaju',
            self::Admin => 'Admin',
            self::Finance => 'Finance',
            self::Head => 'Head of Digitaliz',
        };
    }
}
