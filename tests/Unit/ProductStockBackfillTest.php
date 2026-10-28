<?php

namespace Tests\Unit;

use App\Services\ProductStockBackfillService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use RuntimeException;

class ProductStockBackfillTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! app()->environment('testing')) {
            throw new RuntimeException('Stock backfill tests require APP_ENV=testing.');
        }

        if ($this->usesMariaDb()) {
            if (env('SHOPHELPER_ALLOW_DESTRUCTIVE_TEST_SETUP') !== 'yes'
                || config('database.connections.mysql.database') !== 'shop_helper_backfill_codex_20261027') {
                throw new RuntimeException('MariaDB backfill tests are restricted to the disposable test database.');
            }

            config(['database.default' => 'mysql']);
            DB::purge('mysql');
            DB::setDefaultConnection('mysql');

            $connection = DB::connection('mysql');
            $actualDatabase = $connection->selectOne('SELECT DATABASE() AS database_name')->database_name ?? null;
            if ($connection->getDriverName() !== 'mysql'
                || $actualDatabase !== 'shop_helper_backfill_codex_20261027') {
                throw new RuntimeException('Refusing to drop fixtures outside the resolved disposable MariaDB database.');
            }

            $this->dropFixtureTables();
        } else {
            config(['database.default' => 'sqlite']);
            config(['database.connections.sqlite.database' => ':memory:']);
            DB::purge('sqlite');
            DB::setDefaultConnection('sqlite');
        }

        Schema::create('shop_owners', function (Blueprint $table): void {
            $table->id();
            $table->string('shop_name');
        });
        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_owner_id')->constrained()->cascadeOnDelete();
            $table->string('name');
        });
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_owner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('sku');
            $table->decimal('current_stock', 15, 4)->default(0);
        });
        $schemaMigration = require base_path('database/migrations/2026_10_27_000001_create_product_stocks_table.php');
        $schemaMigration->up();
    }

    protected function tearDown(): void
    {
        if ($this->usesMariaDb()) {
            $this->dropFixtureTables();
        }

        parent::tearDown();
    }

    public function test_preview_is_read_only_and_classifies_migrated_unresolved_and_zero_balances(): void
    {
        $this->fixtures();
        $preview = app(ProductStockBackfillService::class)->preview()->keyBy('product_id');

        $this->assertSame('migrated', $preview[1]['status']);
        $this->assertSame('unresolved', $preview[2]['status']);
        $this->assertSame('unresolved', $preview[3]['status']);
        $this->assertSame('zero_balance', $preview[4]['status']);
        $this->assertSame('zero_balance', $preview[5]['status']);
        $this->assertSame('zero_balance', $preview[6]['status']);
        $this->assertSame(0, DB::table('product_stocks')->count());
        $this->assertFalse(Schema::hasTable('stock_reconciliation_log'));
        $this->assertSame('266.0000', $this->quantity(DB::table('products')->sum('current_stock')));
    }

    public function test_partial_failure_can_resume_without_duplicate_stock_or_quantity(): void
    {
        $this->validBranch();
        $this->product(1, 1, 1, '10.0000');
        $this->product(2, 1, 1, '20.0000');
        $this->createLogTable();
        if ($this->usesMariaDb()) {
            DB::unprepared("CREATE TRIGGER fail_second_log BEFORE INSERT ON stock_reconciliation_log FOR EACH ROW BEGIN IF NEW.product_id = 2 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'simulated partial failure'; END IF; END");
        } else {
            DB::statement("CREATE TRIGGER fail_second_log BEFORE INSERT ON stock_reconciliation_log WHEN NEW.product_id = 2 BEGIN SELECT RAISE(ABORT, 'simulated partial failure'); END");
        }

        try {
            app(ProductStockBackfillService::class)->backfill();
            $this->fail('The simulated insert failure should abort the backfill.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertSame(1, DB::table('product_stocks')->count());
            $this->assertSame(1, DB::table('stock_reconciliation_log')->count());
            $this->assertSame('10.0000', $this->quantity(DB::table('product_stocks')->value('current_stock')));
        }

        DB::statement('DROP TRIGGER fail_second_log');
        $service = app(ProductStockBackfillService::class);
        $service->backfill();
        $service->backfill();

        $this->assertSame(2, DB::table('product_stocks')->count());
        $this->assertSame(2, DB::table('stock_reconciliation_log')->count());
        $this->assertSame('30.0000', $this->quantity(DB::table('product_stocks')->sum('current_stock')));
        $this->assertSame('30.0000', $this->quantity(DB::table('products')->sum('current_stock')));
    }

    public function test_existing_stock_without_a_log_is_preserved_and_marked_unresolved(): void
    {
        $this->validBranch();
        $this->product(1, 1, 1, '15.0000');
        $this->createLogTable();
        DB::table('product_stocks')->insert([
            'product_id' => 1, 'branch_id' => 1, 'shop_owner_id' => 1,
            'current_stock' => '99.0000', 'created_at' => now(), 'updated_at' => now(),
        ]);

        app(ProductStockBackfillService::class)->backfill();
        app(ProductStockBackfillService::class)->backfill();

        $this->assertSame('99.0000', $this->quantity(DB::table('product_stocks')->value('current_stock')));
        $this->assertSame(1, DB::table('product_stocks')->count());
        $this->assertSame('unresolved', DB::table('stock_reconciliation_log')->value('status'));
        $this->assertStringContainsString('Existing branch stock', DB::table('stock_reconciliation_log')->value('reason'));
    }

    public function test_zero_legacy_stock_with_an_existing_tracked_row_is_a_conflict(): void
    {
        $this->validBranch();
        $this->product(1, 1, 1, '0.0000');
        $this->createLogTable();
        DB::table('product_stocks')->insert([
            'product_id' => 1, 'branch_id' => 1, 'shop_owner_id' => 1,
            'current_stock' => '8.0000', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $service = app(ProductStockBackfillService::class);
        $preview = $service->preview()->first();
        $this->assertSame('conflict', $preview['status']);
        $this->assertStringContainsString('legacy stock is zero', $preview['reason']);
        $this->assertConflict(fn () => $service->backfill(), 'legacy stock is zero');
        $this->assertSame('8.0000', $this->quantity(DB::table('product_stocks')->value('current_stock')));
        $this->assertSame(0, DB::table('stock_reconciliation_log')->count());
    }

    public function test_zero_balance_retry_conflicts_if_a_tracked_row_appears(): void
    {
        $this->validBranch();
        $this->product(1, 1, 1, '0.0000');
        $this->createLogTable();
        $service = app(ProductStockBackfillService::class);
        $service->backfill();

        DB::table('product_stocks')->insert([
            'product_id' => 1, 'branch_id' => 1, 'shop_owner_id' => 1,
            'current_stock' => '8.0000', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertConflict(fn () => $service->backfill(), 'recorded as zero balance');
        $this->assertSame('8.0000', $this->quantity(DB::table('product_stocks')->value('current_stock')));
        $this->assertSame('zero_balance', DB::table('stock_reconciliation_log')->value('status'));
    }

    public function test_changed_product_or_branch_ownership_fails_closed_on_retry(): void
    {
        $this->validBranch();
        DB::table('shop_owners')->insert(['id' => 2, 'shop_name' => 'Shop Two']);
        DB::table('branches')->insert(['id' => 2, 'shop_owner_id' => 2, 'name' => 'Other Store']);
        $this->product(1, 1, 1, '47.0000');
        $this->createLogTable();
        $service = app(ProductStockBackfillService::class);
        $service->backfill();

        DB::table('products')->where('id', 1)->update(['shop_owner_id' => 2]);
        $this->assertConflict(fn () => $service->backfill(), 'Product ownership changed');

        DB::table('products')->where('id', 1)->update(['shop_owner_id' => 1]);
        DB::table('products')->where('id', 1)->update(['current_stock' => '46.0000']);
        $this->assertConflict(fn () => $service->backfill(), 'source quantity changed');

        DB::table('products')->where('id', 1)->update(['current_stock' => '47.0000']);
        DB::table('branches')->where('id', 1)->update(['shop_owner_id' => 2]);
        $this->assertConflict(fn () => $service->backfill(), 'destination branch');
        $this->assertSame('47.0000', $this->quantity(DB::table('product_stocks')->value('current_stock')));
    }

    public function test_unknown_reconciliation_status_fails_closed(): void
    {
        $this->validBranch();
        $this->product(1, 1, 1, '10.0000');
        $this->createLogTable();
        $service = app(ProductStockBackfillService::class);
        $service->backfill();
        DB::table('stock_reconciliation_log')->where('product_id', 1)->update(['status' => 'pending']);

        $this->assertConflict(fn () => $service->backfill(), 'Unknown reconciliation status');
        $this->assertSame(1, DB::table('product_stocks')->count());
    }

    public function test_migration_records_zero_and_invalid_branch_outcomes_and_refuses_destructive_rollback(): void
    {
        $this->fixtures();
        $migration = require base_path('database/migrations/2026_10_27_000002_backfill_product_stocks_from_products.php');
        $this->runBackfillMigrationOutsideTesting($migration);

        $statuses = DB::table('stock_reconciliation_log')->orderBy('product_id')->pluck('status')->all();
        $this->assertSame(['migrated', 'unresolved', 'unresolved', 'zero_balance', 'zero_balance', 'zero_balance'], $statuses);
        $this->assertSame(1, DB::table('product_stocks')->count());
        $this->assertSame('47.0000', $this->quantity(DB::table('product_stocks')->sum('current_stock')));
        $this->assertSame('266.0000', $this->quantity(DB::table('products')->sum('current_stock')));
        $this->assertSame(6, DB::table('stock_reconciliation_log')->count());
        $this->assertCount(0, Schema::getForeignKeys('stock_reconciliation_log'));
        app(ProductStockBackfillService::class)->backfill();

        try {
            $migration->down();
            $this->fail('The forward-only backfill must refuse rollback.');
        } catch (RuntimeException) {
            $this->assertTrue(Schema::hasTable('product_stocks'));
            $this->assertTrue(Schema::hasTable('stock_reconciliation_log'));
        }
    }

    public function test_backfill_migration_skips_copying_in_testing_environment(): void
    {
        $this->fixtures();
        $migration = require base_path('database/migrations/2026_10_27_000002_backfill_product_stocks_from_products.php');

        $migration->up();

        $this->assertTrue(Schema::hasTable('stock_reconciliation_log'));
        $this->assertSame(0, DB::table('product_stocks')->count());
        $this->assertSame(0, DB::table('stock_reconciliation_log')->count());
        $this->assertSame('266.0000', $this->quantity(DB::table('products')->sum('current_stock')));
    }

    public function test_product_stock_schema_rollback_refuses_to_drop_rows(): void
    {
        $this->validBranch();
        $this->product(1, 1, 1, '8.0000');
        DB::table('product_stocks')->insert([
            'product_id' => 1, 'branch_id' => 1, 'shop_owner_id' => 1,
            'current_stock' => '8.0000', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $migration = require base_path('database/migrations/2026_10_27_000001_create_product_stocks_table.php');

        try {
            $migration->down();
            $this->fail('The schema migration must refuse to drop populated stock rows.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('while stock rows exist', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasTable('product_stocks'));
        $this->assertSame('8.0000', $this->quantity(DB::table('product_stocks')->value('current_stock')));
        $this->assertTrue(Schema::hasTable('products'));
        $this->assertTrue(Schema::hasTable('branches'));
        $this->assertSame('Product 1', DB::table('products')->where('id', 1)->value('name'));
    }

    public function test_product_stock_schema_rollback_drops_empty_table_only(): void
    {
        $this->validBranch();
        $this->product(1, 1, 1, '8.0000');
        $migration = require base_path('database/migrations/2026_10_27_000001_create_product_stocks_table.php');

        $migration->down();

        $this->assertFalse(Schema::hasTable('product_stocks'));
        $this->assertTrue(Schema::hasTable('products'));
        $this->assertTrue(Schema::hasTable('branches'));
        $this->assertTrue(Schema::hasTable('shop_owners'));
        $this->assertSame('Product 1', DB::table('products')->where('id', 1)->value('name'));
    }

    public function test_product_stock_schema_enforces_unique_pairs_and_branch_foreign_key(): void
    {
        $this->validBranch();
        $this->product(1, 1, 1, '8.0000');
        $stock = [
            'product_id' => 1, 'branch_id' => 1, 'shop_owner_id' => 1,
            'current_stock' => '8.0000', 'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('product_stocks')->insert($stock);

        try {
            DB::table('product_stocks')->insert($stock);
            $this->fail('The product/branch unique constraint should reject a duplicate pair.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertSame(1, DB::table('product_stocks')->count());
        }

        try {
            DB::table('branches')->where('id', 1)->delete();
            $this->fail('The branch foreign key should restrict deleting a branch with stock.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertSame(1, DB::table('branches')->count());
            $this->assertSame(1, DB::table('product_stocks')->count());
        }
    }

    public function test_audit_snapshots_survive_hard_deletion_of_product_shop_and_branch(): void
    {
        $this->validBranch();
        $this->product(1, 1, 1, '47.0000');
        $migration = require base_path('database/migrations/2026_10_27_000002_backfill_product_stocks_from_products.php');
        $this->runBackfillMigrationOutsideTesting($migration);

        DB::table('products')->where('id', 1)->delete();
        DB::table('branches')->where('id', 1)->delete();
        DB::table('shop_owners')->where('id', 1)->delete();

        $log = DB::table('stock_reconciliation_log')->where('product_id', 1)->first();
        $this->assertNotNull($log);
        $this->assertSame('Shop One', $log->shop_name_snapshot);
        $this->assertSame('Product 1', $log->product_name_snapshot);
        $this->assertSame('SKU-1', $log->sku_snapshot);
        $this->assertSame('Main Store', $log->source_branch_name_snapshot);
        $this->assertSame('Main Store', $log->destination_branch_name_snapshot);
        $this->assertSame('47.0000', $this->quantity($log->original_stock));
        $this->assertSame('migrated', $log->status);
    }

    public function test_zero_balance_record_conflicts_if_quantity_changes_before_retry(): void
    {
        $this->validBranch();
        $this->product(1, 1, 1, '0.0000');
        $this->createLogTable();
        $service = app(ProductStockBackfillService::class);
        $service->backfill();
        DB::table('products')->where('id', 1)->update(['current_stock' => '1.0000']);

        $this->assertConflict(fn () => $service->backfill(), 'source quantity changed');
        $this->assertSame(0, DB::table('product_stocks')->count());
    }

    public function test_zero_balance_record_conflicts_if_branch_ownership_changes(): void
    {
        $this->validBranch();
        DB::table('shop_owners')->insert(['id' => 2, 'shop_name' => 'Shop Two']);
        DB::table('branches')->insert(['id' => 2, 'shop_owner_id' => 2, 'name' => 'Other Store']);
        $this->product(1, 1, 2, '0.0000');
        $this->createLogTable();
        $service = app(ProductStockBackfillService::class);
        $service->backfill();
        DB::table('branches')->where('id', 2)->update(['shop_owner_id' => 1]);

        $this->assertConflict(fn () => $service->backfill(), 'zero-balance outcome');
        $this->assertSame(0, DB::table('product_stocks')->count());
    }

    private function fixtures(): void
    {
        $this->validBranch();
        DB::table('shop_owners')->insert(['id' => 2, 'shop_name' => 'Shop Two']);
        DB::table('branches')->insert(['id' => 2, 'shop_owner_id' => 2, 'name' => 'Other Store']);
        $this->product(1, 1, 1, '47.0000');
        $this->product(2, 1, null, '100.0000');
        $this->product(3, 1, 2, '119.0000');
        $this->product(4, 1, 1, '0.0000');
        $this->product(5, 1, null, '0.0000');
        $this->product(6, 1, 2, '0.0000');
    }

    private function validBranch(): void
    {
        DB::table('shop_owners')->insert(['id' => 1, 'shop_name' => 'Shop One']);
        DB::table('branches')->insert(['id' => 1, 'shop_owner_id' => 1, 'name' => 'Main Store']);
    }

    private function product(int $id, int $shop, ?int $branch, string $stock): void
    {
        DB::table('products')->insert([
            'id' => $id,
            'shop_owner_id' => $shop,
            'branch_id' => $branch,
            'name' => "Product {$id}",
            'sku' => "SKU-{$id}",
            'current_stock' => $stock,
        ]);
    }

    private function createLogTable(): void
    {
        Schema::create('stock_reconciliation_log', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->unique();
            $table->unsignedBigInteger('shop_owner_id');
            $table->string('shop_name_snapshot')->nullable();
            $table->string('product_name_snapshot');
            $table->string('sku_snapshot')->nullable();
            $table->unsignedBigInteger('source_branch_id')->nullable();
            $table->string('source_branch_name_snapshot')->nullable();
            $table->unsignedBigInteger('destination_branch_id')->nullable();
            $table->string('destination_branch_name_snapshot')->nullable();
            $table->decimal('original_stock', 15, 4);
            $table->string('status', 32);
            $table->string('reason_code', 32);
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    private function runBackfillMigrationOutsideTesting(object $migration): void
    {
        $originalEnvironment = app()->environment();
        $this->app['env'] = 'local';

        try {
            $migration->up();
        } finally {
            $this->app['env'] = $originalEnvironment;
        }
    }

    private function assertConflict(callable $attempt, string $message): void
    {
        try {
            $attempt();
            $this->fail('The inconsistent reconciliation record should stop the retry.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }
    }

    private function usesMariaDb(): bool
    {
        return getenv('STOCK_BACKFILL_TEST_CONNECTION') === 'mysql';
    }

    private function dropFixtureTables(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS fail_second_log');

        foreach (['stock_reconciliation_log', 'product_stocks', 'products', 'branches', 'shop_owners'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function quantity(mixed $value): string
    {
        return number_format((float) $value, 4, '.', '');
    }
}
