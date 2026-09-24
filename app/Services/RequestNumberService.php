<?php

namespace App\Services;

use App\Models\PettyCashRequest;

class RequestNumberService
{
    public const PREFIX = 'KC';

    public function next(): string
    {
        $year = now()->format('Y');

        $last = PettyCashRequest::query()
            ->where('request_number', 'like', self::PREFIX.'-'.$year.'-%')
            ->orderByDesc('request_number')
            ->value('request_number');

        $sequence = 1;

        if ($last !== null) {
            $sequence = ((int) substr($last, strrpos($last, '-') + 1)) + 1;
        }

        return sprintf('%s-%s-%04d', self::PREFIX, $year, $sequence);
    }
}
