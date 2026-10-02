<?php

namespace Tests\Unit;

use App\Enums\StockMode;
use Tests\TestCase;

class StockModeEnumTest extends TestCase
{
    public function test_stock_mode_enum_has_correct_values()
    {
        $this->assertEquals('own', StockMode::Own->value);
        $this->assertEquals('from_variants', StockMode::FromVariants->value);
        $this->assertEquals('from_components', StockMode::FromComponents->value);
    }

    public function test_stock_mode_enum_can_be_created_from_value()
    {
        $own = StockMode::from('own');
        $this->assertEquals(StockMode::Own, $own);

        $fromVariants = StockMode::from('from_variants');
        $this->assertEquals(StockMode::FromVariants, $fromVariants);

        $fromComponents = StockMode::from('from_components');
        $this->assertEquals(StockMode::FromComponents, $fromComponents);
    }

    public function test_stock_mode_enum_cases()
    {
        $cases = StockMode::cases();

        $this->assertCount(3, $cases);
        $this->assertContains(StockMode::Own, $cases);
        $this->assertContains(StockMode::FromVariants, $cases);
        $this->assertContains(StockMode::FromComponents, $cases);
    }

    public function test_stock_mode_enum_is_string_backed()
    {
        $this->assertIsString(StockMode::Own->value);
        $this->assertIsString(StockMode::FromVariants->value);
        $this->assertIsString(StockMode::FromComponents->value);
    }
}
