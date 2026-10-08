<?php

namespace App\Support;

class Phone
{
    /**
     * Normalize an Indonesian WhatsApp number to the 62xxxxxxxxxx form the
     * WA gateway expects. Accepts "0812…", "+62 812-…", "62812…" or "812…".
     */
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '' || $digits === null) {
            return null;
        }

        return match (true) {
            str_starts_with($digits, '62') => $digits,
            str_starts_with($digits, '0') => '62'.substr($digits, 1),
            str_starts_with($digits, '8') => '62'.$digits,
            default => $digits,
        };
    }

    public static function isValid(?string $normalized): bool
    {
        return $normalized !== null && preg_match('/^628\d{7,12}$/', $normalized) === 1;
    }

    /**
     * Validation rule closure usable in Livewire/FormRequest rule arrays.
     */
    public static function rule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (blank($value)) {
                return;
            }

            if (! self::isValid(self::normalize((string) $value))) {
                $fail('Nomor WhatsApp tidak valid. Gunakan format 08xx atau 628xx.');
            }
        };
    }
}
