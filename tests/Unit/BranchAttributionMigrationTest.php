<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class BranchAttributionMigrationTest extends TestCase
{
    public function test_rollback_refuses_to_drop_attribution_when_sales_have_a_branch(): void
    {
        DB::shouldReceive('table')->once()->with('sales')->andReturn($this->queryResult(true));
        Schema::shouldReceive('table')->never();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot roll back branch attribution');

        $this->migration()->down();
    }

    public function test_rollback_refuses_to_drop_attribution_when_movements_have_a_branch(): void
    {
        DB::shouldReceive('table')->once()->with('sales')->andReturn($this->queryResult(false));
        DB::shouldReceive('table')->once()->with('inventory_movements')->andReturn($this->queryResult(true));
        Schema::shouldReceive('table')->never();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot roll back branch attribution');

        $this->migration()->down();
    }

    public function test_rollback_proceeds_when_no_records_have_branch_attribution(): void
    {
        DB::shouldReceive('table')->once()->with('sales')->andReturn($this->queryResult(false));
        DB::shouldReceive('table')->once()->with('inventory_movements')->andReturn($this->queryResult(false));
        Schema::shouldReceive('table')->twice();

        $this->migration()->down();
    }

    private function queryResult(bool $hasAttribution): object
    {
        $query = Mockery::mock();
        $query->shouldReceive('whereNotNull')->once()->with('branch_id')->andReturnSelf();
        $query->shouldReceive('exists')->once()->andReturn($hasAttribution);

        return $query;
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_27_000003_add_branch_attribution_to_sales_and_inventory_movements.php');
    }
}
