<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_movements') || ! Schema::hasTable('warehouses')) {
            throw new RuntimeException('Run the inventory and warehouse migrations before adding warehouse movement attribution.');
        }

        if (! Schema::hasColumn('inventory_movements', 'warehouse_id')) {
            Schema::table('inventory_movements', function (Blueprint $table): void {
                $table->unsignedBigInteger('warehouse_id')->nullable()->after('branch_id');
            });
        } else {
            $column = collect(Schema::getColumns('inventory_movements'))->firstWhere('name', 'warehouse_id');
            $type = strtolower(trim((string) ($column['type'] ?? '')));
            $type = preg_replace('/\(\d+\)/', '', $type) ?? $type;
            $validType = DB::getDriverName() === 'sqlite'
                ? in_array($type, ['integer', 'bigint unsigned'], true)
                : $type === 'bigint unsigned';
            if (! $column || ! ($column['nullable'] ?? false) || ! $validType) {
                throw new RuntimeException('Cannot safely resume warehouse attribution: inventory_movements.warehouse_id has an incompatible definition.');
            }
        }

        $indexes = collect(Schema::getIndexes('inventory_movements'));
        $unexpectedIndex = $indexes->first(fn ($index) => in_array('warehouse_id', $index['columns'] ?? [], true)
            && ($index['name'] ?? null) !== 'inventory_movements_warehouse_created_index');
        if ($unexpectedIndex) throw new RuntimeException("Cannot safely resume warehouse attribution: unexpected index {$unexpectedIndex['name']} uses warehouse_id.");
        $namedIndex = $indexes->firstWhere('name', 'inventory_movements_warehouse_created_index');
        if ($namedIndex && ($namedIndex['columns'] ?? []) !== ['warehouse_id', 'created_at']) {
            throw new RuntimeException('Cannot safely resume warehouse attribution: the warehouse movement index has an incompatible definition.');
        }
        if (! $namedIndex) Schema::table('inventory_movements', fn (Blueprint $table) => $table->index(['warehouse_id', 'created_at'], 'inventory_movements_warehouse_created_index'));

        $foreignKeys = collect(Schema::getForeignKeys('inventory_movements'));
        $namedForeign = $foreignKeys->firstWhere('name', 'inventory_movements_warehouse_id_foreign');
        if ($namedForeign) {
            if (($namedForeign['columns'] ?? []) !== ['warehouse_id']
                || ($namedForeign['foreign_table'] ?? null) !== 'warehouses'
                || ($namedForeign['foreign_columns'] ?? []) !== ['id']
                || strtolower($namedForeign['on_delete'] ?? '') !== 'restrict') {
                throw new RuntimeException('Cannot safely resume warehouse attribution: the warehouse foreign key has an incompatible definition.');
            }
        } else {
            if ($foreignKeys->contains(fn ($key) => ($key['columns'] ?? []) === ['warehouse_id'])) {
                throw new RuntimeException('Cannot safely resume warehouse attribution: warehouse_id has an unexpected foreign key.');
            }
            Schema::table('inventory_movements', fn (Blueprint $table) => $table->foreign('warehouse_id', 'inventory_movements_warehouse_id_foreign')->references('id')->on('warehouses')->restrictOnDelete());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('inventory_movements', 'warehouse_id')
            && DB::table('inventory_movements')->whereNotNull('warehouse_id')->exists()) {
            throw new RuntimeException('Cannot remove warehouse attribution while inventory movements reference warehouses. Preserve history with a reviewed forward migration.');
        }

        if (Schema::hasColumn('inventory_movements', 'warehouse_id')) {
            $foreignKeys = collect(Schema::getForeignKeys('inventory_movements'));
            $foreign = $foreignKeys->firstWhere('name', 'inventory_movements_warehouse_id_foreign');
            if ($foreign && (($foreign['columns'] ?? []) !== ['warehouse_id'] || ($foreign['foreign_table'] ?? null) !== 'warehouses')) {
                throw new RuntimeException('Refusing rollback because the warehouse foreign key has an unexpected definition.');
            }
            if ($foreignKeys->contains(fn ($key) => ($key['columns'] ?? []) === ['warehouse_id'] && ($key['name'] ?? null) !== 'inventory_movements_warehouse_id_foreign')) {
                throw new RuntimeException('Refusing rollback because an unexpected foreign key uses warehouse_id.');
            }
            $indexes = collect(Schema::getIndexes('inventory_movements'));
            $index = $indexes->firstWhere('name', 'inventory_movements_warehouse_created_index');
            if ($index && ($index['columns'] ?? []) !== ['warehouse_id', 'created_at']) {
                throw new RuntimeException('Refusing rollback because the warehouse movement index has an unexpected definition.');
            }
            if ($indexes->contains(fn ($row) => in_array('warehouse_id', $row['columns'] ?? [], true) && ($row['name'] ?? null) !== 'inventory_movements_warehouse_created_index')) {
                throw new RuntimeException('Refusing rollback because an unexpected index uses warehouse_id.');
            }
            Schema::table('inventory_movements', function (Blueprint $table) use ($foreign, $index): void {
                if ($foreign) $table->dropForeign('inventory_movements_warehouse_id_foreign');
                if ($index) $table->dropIndex('inventory_movements_warehouse_created_index');
                $table->dropColumn('warehouse_id');
            });
        }
    }
};
