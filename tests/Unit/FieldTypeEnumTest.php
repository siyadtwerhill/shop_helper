<?php

namespace Tests\Unit;

use App\Enums\FieldType;
use Tests\TestCase;

class FieldTypeEnumTest extends TestCase
{
    public function test_field_type_enum_has_correct_values()
    {
        $this->assertEquals('text', FieldType::Text->value);
        $this->assertEquals('textarea', FieldType::Textarea->value);
        $this->assertEquals('number', FieldType::Number->value);
        $this->assertEquals('decimal', FieldType::Decimal->value);
        $this->assertEquals('select', FieldType::Select->value);
        $this->assertEquals('multi_select', FieldType::MultiSelect->value);
        $this->assertEquals('checkbox', FieldType::Checkbox->value);
        $this->assertEquals('date', FieldType::Date->value);
        $this->assertEquals('boolean', FieldType::Boolean->value);
        $this->assertEquals('json', FieldType::Json->value);
    }

    public function test_field_type_enum_can_be_created_from_value()
    {
        $text = FieldType::from('text');
        $this->assertEquals(FieldType::Text, $text);

        $select = FieldType::from('select');
        $this->assertEquals(FieldType::Select, $select);

        $json = FieldType::from('json');
        $this->assertEquals(FieldType::Json, $json);
    }

    public function test_field_type_enum_cases()
    {
        $cases = FieldType::cases();

        $this->assertCount(10, $cases);
    }

    public function test_field_type_enum_has_all_expected_types()
    {
        $cases = FieldType::cases();
        $values = array_map(fn($case) => $case->value, $cases);

        $this->assertContains('text', $values);
        $this->assertContains('textarea', $values);
        $this->assertContains('number', $values);
        $this->assertContains('decimal', $values);
        $this->assertContains('select', $values);
        $this->assertContains('multi_select', $values);
        $this->assertContains('checkbox', $values);
        $this->assertContains('date', $values);
        $this->assertContains('boolean', $values);
        $this->assertContains('json', $values);
    }

    public function test_field_type_enum_is_string_backed()
    {
        foreach (FieldType::cases() as $case) {
            $this->assertIsString($case->value);
        }
    }
}
