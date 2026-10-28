<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\QueryException;
use RuntimeException;

class BranchAttributionMigrationMariaDbTest extends BaseTestCase
{
    private bool $fixtureDatabaseVerified = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('BRANCH_ATTRIBUTION_MARIADB_INTEGRATION') !== 'yes') {
            $this->markTestSkipped('Set BRANCH_ATTRIBUTION_MARIADB_INTEGRATION=yes to run the isolated MariaDB migration test.');
        }

        if (! app()->environment('testing')
            || config('database.default') !== 'mysql'
            || config('database.connections.mysql.database') !== 'shop_helper_branch_attribution_codex_20261027'
            || env('SHOPHELPER_ALLOW_DESTRUCTIVE_TEST_SETUP') !== 'yes') {
            throw new RuntimeException('Refusing schema fixture setup outside the explicitly opted-in MariaDB test database.');
        }

        config(['database.default' => 'mysql']);
        DB::purge('mysql');
        DB::setDefaultConnection('mysql');
        $connection = DB::connection('mysql');
        $actualDatabase = $connection->selectOne('SELECT DATABASE() AS database_name')->database_name ?? null;
        $serverVersion = $connection->selectOne('SELECT VERSION() AS version')->version ?? '';

        if ($connection->getDriverName() !== 'mysql'
            || $actualDatabase !== 'shop_helper_branch_attribution_codex_20261027'
            || ! str_contains($serverVersion, 'MariaDB')) {
            throw new RuntimeException('Refusing fixtures: the resolved database is not the dedicated MariaDB test database.');
        }

        foreach (['sales', 'inventory_movements', 'branches'] as $table) {
            if (Schema::hasTable($table)) {
                throw new RuntimeException("Refusing fixtures because {$table} already exists in the disposable database.");
            }
        }

        $this->fixtureDatabaseVerified = true;
        $this->createBaseSchema();
        $this->migration()->up();
    }

    protected function tearDown(): void
    {
        if ($this->fixtureDatabaseVerified) {
            Schema::dropIfExists('sales');
            Schema::dropIfExists('inventory_movements');
            Schema::dropIfExists('branches');
        }

        parent::tearDown();
    }

    public function test_sales_attribution_prevents_rollback_without_changing_schema_or_data(): void
    {
        DB::table('branches')->insert(['id' => 1]);
        DB::table('sales')->insert(['id' => 1, 'shop_owner_id' => 10, 'branch_id' => 1]);

        try {
            $this->migration()->down();
            $this->fail('Rollback should be refused while an attributed sale exists.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Cannot roll back branch attribution', $exception->getMessage());
        }

        $this->assertAttributionSchema();
        $this->assertSame(1, (int) DB::table('sales')->where('id', 1)->value('branch_id'));
    }

    public function test_movement_attribution_prevents_rollback_without_changing_schema_or_data(): void
    {
        DB::table('branches')->insert(['id' => 1]);
        DB::table('inventory_movements')->insert([
            'id' => 1,
            'product_id' => 20,
            'branch_id' => 1,
            'created_at' => now(),
        ]);

        try {
            $this->migration()->down();
            $this->fail('Rollback should be refused while an attributed movement exists.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Cannot roll back branch attribution', $exception->getMessage());
        }

        $this->assertAttributionSchema();
        $this->assertSame(1, (int) DB::table('inventory_movements')->where('id', 1)->value('branch_id'));
    }

    public function test_rollback_removes_only_the_attribution_schema_objects_when_no_records_are_attributed(): void
    {
        $this->migration()->down();

        foreach (['sales', 'inventory_movements'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'branch_id'));
            $this->assertSame(0, $this->indexCount($table));
            $this->assertSame(0, $this->foreignKeyCount($table));
        }

        $this->assertTrue(Schema::hasColumn('sales', 'shop_owner_id'));
        $this->assertTrue(Schema::hasColumn('inventory_movements', 'product_id'));
        $this->assertTrue(Schema::hasColumn('inventory_movements', 'created_at'));
        $this->assertTrue(Schema::hasTable('branches'));
        $this->assertSame(50, (int) DB::table('sales')->value('id'));
        $this->assertSame(60, (int) DB::table('inventory_movements')->value('id'));
    }

    public function test_migration_adds_nullable_restrictive_foreign_keys_and_preserves_existing_rows(): void
    {
        $this->assertAttributionSchema();

        $salesBranch = collect(Schema::getColumns('sales'))->firstWhere('name', 'branch_id');
        $movementBranch = collect(Schema::getColumns('inventory_movements'))->firstWhere('name', 'branch_id');
        $this->assertTrue($salesBranch['nullable']);
        $this->assertTrue($movementBranch['nullable']);
        $this->assertNull(DB::table('sales')->where('id', 50)->value('branch_id'));
        $this->assertNull(DB::table('inventory_movements')->where('id', 60)->value('branch_id'));
        $this->assertSame(['shop_owner_id', 'branch_id'], $this->indexColumns('sales', 'sales_shop_owner_id_branch_id_index'));
        $this->assertSame(['branch_id'], $this->indexColumns('sales', 'sales_branch_id_index'));
        $this->assertSame(['branch_id', 'created_at'], $this->indexColumns('inventory_movements', 'inventory_movements_branch_id_created_at_index'));
        $this->assertSame('RESTRICT', strtoupper($this->foreignKeyDeleteRule('sales')));
        $this->assertSame('RESTRICT', strtoupper($this->foreignKeyDeleteRule('inventory_movements')));
    }

    public function test_invalid_branch_references_are_rejected_for_both_tables(): void
    {
        try {
            DB::table('sales')->insert(['id' => 1, 'shop_owner_id' => 10, 'branch_id' => 999]);
            $this->fail('Sales must reject a branch that does not exist.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('sales', ['id' => 1]);
        }

        try {
            DB::table('inventory_movements')->insert([
                'id' => 1,
                'product_id' => 20,
                'branch_id' => 999,
                'created_at' => now(),
            ]);
            $this->fail('Inventory movements must reject a branch that does not exist.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('inventory_movements', ['id' => 1]);
        }
    }

    public function test_branch_deletion_is_restricted_when_attributed_records_exist(): void
    {
        DB::table('branches')->insert(['id' => 1]);
        DB::table('sales')->insert(['id' => 1, 'shop_owner_id' => 10, 'branch_id' => 1]);
        DB::table('inventory_movements')->insert([
            'id' => 1,
            'product_id' => 20,
            'branch_id' => 1,
            'created_at' => now(),
        ]);

        try {
            DB::table('branches')->where('id', 1)->delete();
            $this->fail('A branch with attributed operational records must not be deleted.');
        } catch (QueryException) {
            $this->assertDatabaseHas('branches', ['id' => 1]);
            $this->assertDatabaseHas('sales', ['id' => 1, 'branch_id' => 1]);
            $this->assertDatabaseHas('inventory_movements', ['id' => 1, 'branch_id' => 1]);
        }
    }

    public function test_partial_up_failure_can_be_retried_without_losing_existing_records(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropForeign('inventory_movements_branch_id_foreign');
            $table->dropIndex('inventory_movements_branch_id_created_at_index');
            $table->dropColumn('branch_id');
        });
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->unsignedBigInteger('branch_id')->nullable()->after('product_id');
            $table->index(['created_at'], 'inventory_movements_branch_id_created_at_index');
        });

        try {
            $this->migration()->up();
            $this->fail('An incompatible object should stop migration retry.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('incompatible definition', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('sales', 'branch_id'));
        $this->assertTrue(Schema::hasColumn('inventory_movements', 'branch_id'));
        $this->assertSame(50, (int) DB::table('sales')->value('id'));
        $this->assertSame(60, (int) DB::table('inventory_movements')->value('id'));

        Schema::table('inventory_movements', fn (Blueprint $table) => $table->dropIndex('inventory_movements_branch_id_created_at_index'));
        $this->migration()->up();

        $this->assertAttributionSchema();
        $this->assertNull(DB::table('sales')->where('id', 50)->value('branch_id'));
        $this->assertNull(DB::table('inventory_movements')->where('id', 60)->value('branch_id'));
    }

    public function test_signed_existing_branch_column_fails_closed_without_schema_or_data_repair(): void
    {
        $this->removeInventoryAttribution();
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->bigInteger('branch_id')->nullable()->after('product_id');
        });

        try {
            $this->migration()->up();
            $this->fail('A signed branch_id column must not be accepted or altered during retry.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('incompatible definition', $exception->getMessage());
        }

        $column = collect(Schema::getColumns('inventory_movements'))->firstWhere('name', 'branch_id');
        $this->assertSame('bigint', preg_replace('/\(\d+\)/', '', strtolower($column['type'])));
        $this->assertTrue($column['nullable']);
        $this->assertSame([], $this->indexColumns('inventory_movements', 'inventory_movements_branch_id_created_at_index'));
        $this->assertSame(0, $this->foreignKeyCount('inventory_movements'));
        $this->assertSame(50, (int) DB::table('sales')->value('id'));
        $this->assertSame(60, (int) DB::table('inventory_movements')->value('id'));
        $this->assertAttributionSchemaFor('sales');
    }

    public function test_unsigned_nullable_existing_branch_column_is_completed_on_retry(): void
    {
        $this->removeInventoryAttribution();
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->unsignedBigInteger('branch_id')->nullable()->after('product_id');
        });

        $this->migration()->up();

        $column = collect(Schema::getColumns('inventory_movements'))->firstWhere('name', 'branch_id');
        $this->assertSame('bigint unsigned', preg_replace('/\(\d+\)/', '', strtolower($column['type'])));
        $this->assertTrue($column['nullable']);
        $this->assertSame(['branch_id', 'created_at'], $this->indexColumns('inventory_movements', 'inventory_movements_branch_id_created_at_index'));
        $this->assertSame(1, $this->foreignKeyCount('inventory_movements'));
        $this->assertNull(DB::table('inventory_movements')->where('id', 60)->value('branch_id'));
    }

    public function test_equivalent_index_with_unexpected_name_fails_closed_for_up_and_down(): void
    {
        $this->removeInventoryAttribution();
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->unsignedBigInteger('branch_id')->nullable()->after('product_id');
            $table->index(['branch_id', 'created_at'], 'manual_branch_created_index');
        });

        try {
            $this->migration()->up();
            $this->fail('An equivalent index not owned by the migration must block up retry.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('not owned by this migration', $exception->getMessage());
        }

        try {
            $this->migration()->down();
            $this->fail('Rollback must also refuse the unexpected equivalent index.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('unexpected index', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('inventory_movements', 'branch_id'));
        $this->assertSame(['branch_id', 'created_at'], $this->indexColumns('inventory_movements', 'manual_branch_created_index'));
        $this->assertSame(0, $this->foreignKeyCount('inventory_movements'));
        $this->assertAttributionSchemaFor('sales');
        $this->assertSame(50, (int) DB::table('sales')->value('id'));
        $this->assertSame(60, (int) DB::table('inventory_movements')->value('id'));
    }

    public function test_existing_correct_schema_is_safe_to_retry(): void
    {
        $this->migration()->up();
        $this->assertAttributionSchema();
        $this->assertNull(DB::table('sales')->where('id', 50)->value('branch_id'));
        $this->assertNull(DB::table('inventory_movements')->where('id', 60)->value('branch_id'));
    }

    private function createBaseSchema(): void
    {
        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('shop_owner_id');
        });
        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->timestamp('created_at')->nullable();
        });
        DB::table('sales')->insert(['id' => 50, 'shop_owner_id' => 10]);
        DB::table('inventory_movements')->insert(['id' => 60, 'product_id' => 20, 'created_at' => now()]);
    }

    private function assertAttributionSchema(): void
    {
        foreach ([
            'sales' => [
                'sales_shop_owner_id_branch_id_index' => ['shop_owner_id', 'branch_id'],
                'sales_branch_id_index' => ['branch_id'],
            ],
            'inventory_movements' => ['inventory_movements_branch_id_created_at_index' => ['branch_id', 'created_at']],
        ] as $table => $indexes) {
            $this->assertTrue(Schema::hasColumn($table, 'branch_id'));
            foreach ($indexes as $name => $columns) {
                $this->assertSame($columns, $this->indexColumns($table, $name));
            }
            $this->assertSame(1, $this->foreignKeyCount($table));
        }
    }

    private function assertAttributionSchemaFor(string $table): void
    {
        $indexes = $table === 'sales'
            ? [
                'sales_shop_owner_id_branch_id_index' => ['shop_owner_id', 'branch_id'],
                'sales_branch_id_index' => ['branch_id'],
            ]
            : ['inventory_movements_branch_id_created_at_index' => ['branch_id', 'created_at']];

        $this->assertTrue(Schema::hasColumn($table, 'branch_id'));
        foreach ($indexes as $name => $columns) {
            $this->assertSame($columns, $this->indexColumns($table, $name));
        }
        $this->assertSame(1, $this->foreignKeyCount($table));
    }

    private function removeInventoryAttribution(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropForeign('inventory_movements_branch_id_foreign');
            $table->dropIndex('inventory_movements_branch_id_created_at_index');
            $table->dropColumn('branch_id');
        });
    }

    private function indexCount(string $table): int
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->whereIn('INDEX_NAME', [
                'sales_shop_owner_id_branch_id_index',
                'sales_branch_id_index',
                'inventory_movements_branch_id_created_at_index',
            ])
            ->distinct()
            ->count('INDEX_NAME');
    }

    private function indexColumns(string $table, string $index): array
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->orderBy('SEQ_IN_INDEX')
            ->pluck('COLUMN_NAME')
            ->all();
    }

    private function foreignKeyCount(string $table): int
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE as k')
            ->join('information_schema.REFERENTIAL_CONSTRAINTS as r', function ($join): void {
                $join->on('r.CONSTRAINT_SCHEMA', '=', 'k.CONSTRAINT_SCHEMA')
                    ->on('r.CONSTRAINT_NAME', '=', 'k.CONSTRAINT_NAME')
                    ->on('r.TABLE_NAME', '=', 'k.TABLE_NAME');
            })
            ->where('k.CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())
            ->where('k.TABLE_NAME', $table)
            ->where('k.COLUMN_NAME', 'branch_id')
            ->where('k.REFERENCED_TABLE_NAME', 'branches')
            ->count();
    }

    private function foreignKeyDeleteRule(string $table): string
    {
        return (string) DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->value('DELETE_RULE');
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_27_000003_add_branch_attribution_to_sales_and_inventory_movements.php');
    }
}
