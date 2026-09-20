<?php

namespace App\Services;

use App\Models\ProductBarcode;

class BarcodeGenerator
{
    // "200" prefix range is reserved by GS1 for internal/in-store use —
    // safe to generate without a real company GS1 registration.
    private const INTERNAL_PREFIX = '200';

    public function generate(): string
    {
        do {
            $body = self::INTERNAL_PREFIX . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
            $candidate = $body . $this->checkDigit($body);
        } while (ProductBarcode::where('barcode', $candidate)->exists());

        return $candidate;
    }

    private function checkDigit(string $twelveDigits): int
    {
        $sum = 0;
        foreach (str_split($twelveDigits) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
        }
        return (10 - ($sum % 10)) % 10;
    }
}