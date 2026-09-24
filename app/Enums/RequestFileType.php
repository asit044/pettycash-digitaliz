<?php

namespace App\Enums;

enum RequestFileType: string
{
    case Invoice = 'invoice';
    case ProofTransfer = 'proof_transfer';
    case OfficialReceipt = 'official_receipt';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Invoice / Struk',
            self::ProofTransfer => 'Bukti Transfer',
            self::OfficialReceipt => 'Bukti Transfer Resmi',
        };
    }
}
