<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['name' => 'Kilogram', 'symbol' => 'KG', 'type' => 'weight'],
            ['name' => 'Gram', 'symbol' => 'G', 'type' => 'weight'],
            ['name' => 'Peiktha', 'symbol' => 'ပိဿာ', 'type' => 'weight'],
            ['name' => 'Kyattha', 'symbol' => 'ကျပ်သား', 'type' => 'weight'],
            ['name' => 'Liter', 'symbol' => 'L', 'type' => 'volume'],
            ['name' => 'Milliliter', 'symbol' => 'ML', 'type' => 'volume'],
            ['name' => 'Meter', 'symbol' => 'M', 'type' => 'length'],
            ['name' => 'Centimeter', 'symbol' => 'CM', 'type' => 'length'],
            ['name' => 'Piece', 'symbol' => 'PC', 'type' => 'quantity'],
            ['name' => 'Dozen', 'symbol' => 'DZ', 'type' => 'quantity'],
            ['name' => 'Pack', 'symbol' => 'PK', 'type' => 'quantity'],
        ];

        foreach ($units as $unit) {
            Unit::updateOrCreate(
                ['shop_owner_id' => null, 'name' => $unit['name']],
                $unit + ['is_system' => true]
            );
        }

        // Note: product-specific packaging units (e.g. a rice seller's "အိတ်" = 20KG,
        // a drinks seller's "ဖာ" = 24 bottles) are NOT seeded here — per the spec,
        // packaging terms are tenant- and product-specific, not universal. Those are
        // created through the Unit API with shop_owner_id set and is_system = false.
    }
}