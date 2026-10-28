<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'sales' => [
            'after' => 'shop_owner_id',
            'index' => 'sales_shop_owner_id_branch_id_index',
            'index_columns' => ['shop_owner_id', 'branch_id'],
            'support_index' => 'sales_branch_id_index',
            'foreign' => 'sales_branch_id_foreign',
        ],
        'inventory_movements' => [
            'after' => 'product_id',
            'index' => 'inventory_movements_branch_id_created_at_index',
            'index_columns' => ['branch_id', 'created_at'],
            'support_index' => null,
            'foreign' => 'inventory_movements_branch_id_foreign',
        ],
    ];

    public function up(): void
    {
        foreach (array_merge(array_keys(self::TABLES), ['branches']) as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Cannot add branch attribution: required table {$table} is missing.");
            }
        }

        foreach (self::TABLES as $table => $definition) {
            $this->ensureBranchColumn($table, $definition['after']);
            $this->ensureIndex($table, $definition['index'], $definition['index_columns']);
            if ($definition['support_index']) {
                $this->ensureIndex($table, $definition['support_index'], ['branch_id']);
            }
            $this->ensureForeignKey($table, $definition['foreign']);
        }
    }

    public function down(): void
    {
        // Run only in a maintenance window with application writes stopped. The check
        // prevents data loss but cannot prevent a concurrent write racing this DDL.
        foreach (array_keys(self::TABLES) as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'branch_id')
                && DB::table($table)->whereNotNull('branch_id')->exists()) {
                throw new RuntimeException('Cannot roll back branch attribution while attributed sales or inventory movements exist. Preserve the data with a reviewed forward migration.');
            }
        }

        // Preflight both tables before changing either, so a conflicting object does
        // not leave a half-rolled-back migration.
        foreach (self::TABLES as $table => $definition) {
            $this->assertOwnedObjects($table, $definition);
        }

        foreach (self::TABLES as $table => $definition) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            if ($this->foreignKeyExists($table, $definition['foreign'])) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropForeign($definition['foreign']));
            }
            if ($definition['support_index'] && $this->indexExists($table, $definition['support_index'])) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($definition['support_index']));
            }
            if ($this->indexExists($table, $definition['index'])) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($definition['index']));
            }
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('branch_id'));
        }
    }

    private function ensureBranchColumn(string $table, string $after): void
    {
        if (Schema::hasColumn($table, 'branch_id')) {
            $column = collect(Schema::getColumns($table))->firstWhere('name', 'branch_id');
            $type = strtolower(trim((string) ($column['type'] ?? '')));
            $type = preg_replace('/\(\d+\)/', '', $type) ?? $type;
            if (! $column || ! ($column['nullable'] ?? false) || $type !== 'bigint unsigned') {
                throw new RuntimeException("Cannot safely resume branch attribution: {$table}.branch_id has an incompatible definition.");
            }
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($after): void {
            $blueprint->unsignedBigInteger('branch_id')->nullable()->after($after);
        });
    }

    private function ensureIndex(string $table, string $name, array $columns): void
    {
        // Strict ownership: never adopt a differently named branch_id index. Such
        // indexes block both migration retry and rollback until reviewed manually.
        $indexes = collect(Schema::getIndexes($table));
        $ownedNames = array_filter([
            self::TABLES[$table]['index'],
            self::TABLES[$table]['support_index'],
        ]);
        $unexpected = $indexes->first(fn ($index) =>
            in_array('branch_id', $index['columns'] ?? [], true)
            && ! in_array($index['name'] ?? null, $ownedNames, true)
        );
        if ($unexpected) {
            throw new RuntimeException("Cannot safely resume branch attribution: index {$unexpected['name']} on {$table}.branch_id is not owned by this migration.");
        }

        $named = $indexes->firstWhere('name', $name);
        if ($named) {
            if (($named['columns'] ?? []) !== $columns || ($named['unique'] ?? false)) {
                throw new RuntimeException("Cannot safely resume branch attribution: index {$name} has an incompatible definition.");
            }
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
    }

    private function ensureForeignKey(string $table, string $name): void
    {
        $foreignKeys = collect(Schema::getForeignKeys($table));
        $named = $foreignKeys->firstWhere('name', $name);
        if ($named) {
            if (($named['columns'] ?? []) !== ['branch_id']
                || ($named['foreign_table'] ?? null) !== 'branches'
                || ($named['foreign_columns'] ?? []) !== ['id']
                || strtolower($named['on_delete'] ?? '') !== 'restrict') {
                throw new RuntimeException("Cannot safely resume branch attribution: foreign key {$name} has an incompatible definition.");
            }
            return;
        }
        if ($foreignKeys->contains(fn ($key) => ($key['columns'] ?? []) === ['branch_id'])) {
            throw new RuntimeException("Cannot safely resume branch attribution: {$table}.branch_id already has an unexpected foreign key.");
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->foreign('branch_id', $name)->references('id')->on('branches')->restrictOnDelete());
    }

    private function assertOwnedObjects(string $table, array $definition): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'branch_id')) {
            return;
        }
        foreach (Schema::getForeignKeys($table) as $key) {
            if (($key['columns'] ?? []) === ['branch_id']) {
                if (($key['name'] ?? null) !== $definition['foreign']
                    || ($key['foreign_table'] ?? null) !== 'branches'
                    || ($key['foreign_columns'] ?? []) !== ['id']
                    || strtolower($key['on_delete'] ?? '') !== 'restrict') {
                    throw new RuntimeException("Refusing rollback because {$table}.branch_id has an unexpected foreign key.");
                }
            }
        }
        foreach (Schema::getIndexes($table) as $index) {
            if (in_array('branch_id', $index['columns'] ?? [], true)
                && ! in_array($index['name'], array_filter([$definition['index'], $definition['support_index']]), true)
                && ! ($index['primary'] ?? false)) {
                throw new RuntimeException("Refusing rollback because {$table}.branch_id is used by an unexpected index.");
            }
        }
        $namedIndex = collect(Schema::getIndexes($table))->firstWhere('name', $definition['index']);
        if ($namedIndex && ($namedIndex['columns'] ?? []) !== $definition['index_columns']) {
            throw new RuntimeException("Refusing rollback because index {$definition['index']} has an unexpected definition.");
        }
        if ($definition['support_index']) {
            $supportIndex = collect(Schema::getIndexes($table))->firstWhere('name', $definition['support_index']);
            if ($supportIndex && ($supportIndex['columns'] ?? []) !== ['branch_id']) {
                throw new RuntimeException("Refusing rollback because index {$definition['support_index']} has an unexpected definition.");
            }
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn ($index) => ($index['name'] ?? null) === $name);
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        return collect(Schema::getForeignKeys($table))->contains(fn ($key) => ($key['name'] ?? null) === $name);
    }
};
