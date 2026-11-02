<?php

namespace App\Services;

use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ProductStockBackfillService
{
    public function preview(): Collection
    {
        $hasLog = Schema::hasTable('stock_reconciliation_log');

        return $this->products()->get()->map(fn (object $product) => $this->classify($product, $hasLog));
    }

    public function backfill(): void
    {
        if (! Schema::hasTable('product_stocks') || ! Schema::hasTable('stock_reconciliation_log')) {
            throw new RuntimeException('The product stock and reconciliation tables must exist before backfilling.');
        }

        if (! Schema::hasColumns('stock_reconciliation_log', $this->logColumns())) {
            throw new RuntimeException('stock_reconciliation_log has an incompatible schema; preserve it and reconcile it before retrying.');
        }

        DB::table('products')->select('id')->orderBy('id')->chunkById(200, function (Collection $products): void {
            foreach ($products as $row) {
                DB::transaction(function () use ($row): void {
                    $locked = DB::table('products')->where('id', $row->id)->lockForUpdate()->first();
                    $product = $locked ? $this->products()->where('products.id', $row->id)->first() : null;

                    if (! $product) {
                        return;
                    }

                    $this->lockSourceBranch($product);
                    $result = $this->classify($product, true);

                    if ($result['status'] === 'conflict') {
                        throw new RuntimeException("Stock backfill conflict for product {$product->product_id}: {$result['reason']}");
                    }

                    if ($result['action'] !== 'would record') {
                        return;
                    }

                    if ($result['status'] === 'migrated') {
                        DB::table('product_stocks')->insert([
                            'product_id' => $result['product_id'],
                            'branch_id' => $result['destination_branch_id'],
                            'shop_owner_id' => $result['shop_owner_id'],
                            'current_stock' => $result['original_stock'],
                            'reorder_level' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $logData = [
                        'product_id' => $result['product_id'],
                        'shop_owner_id' => $result['shop_owner_id'],
                        'shop_name_snapshot' => $result['shop_name'],
                        'product_name_snapshot' => $result['product_name'],
                        'sku_snapshot' => $result['sku'],
                        'source_branch_id' => $result['source_branch_id'],
                        'source_branch_name_snapshot' => $result['source_branch_name'],
                        'destination_branch_id' => $result['destination_branch_id'],
                        'destination_branch_name_snapshot' => $result['destination_branch_name'],
                        'original_stock' => $result['original_stock'],
                        'status' => $result['status'],
                        'reason_code' => $result['reason_code'],
                        'reason' => $result['reason'],
                        'updated_at' => now(),
                    ];
                    $existingLog = DB::table('stock_reconciliation_log')
                        ->where('product_id', $result['product_id'])
                        ->lockForUpdate()
                        ->first();

                    if ($existingLog) {
                        // A missing-branch outcome can be safely retried after
                        // the first branch exists; retain the original audit row.
                        DB::table('stock_reconciliation_log')
                            ->where('product_id', $result['product_id'])
                            ->update(Arr::only($logData, [
                                'destination_branch_id', 'destination_branch_name_snapshot',
                                'status', 'reason_code', 'reason', 'updated_at',
                            ]));
                    } else {
                        $logData['created_at'] = now();
                        DB::table('stock_reconciliation_log')->insert($logData);
                    }
                }, 3);
            }
        });
    }

    private function products()
    {
        return DB::table('products')
            ->leftJoin('shop_owners', 'shop_owners.id', '=', 'products.shop_owner_id')
            ->leftJoin('branches as source_branch_by_id', 'source_branch_by_id.id', '=', 'products.branch_id')
            ->leftJoin('branches as source_branches', function (JoinClause $join): void {
                $join->on('source_branches.id', '=', 'products.branch_id')
                    ->on('source_branches.shop_owner_id', '=', 'products.shop_owner_id');
            })
            ->addSelect([
                'fallback_branch_id' => DB::table('branches as first_branch')
                    ->select('first_branch.id')
                    ->whereColumn('first_branch.shop_owner_id', 'products.shop_owner_id')
                    ->where('first_branch.is_active', true)
                    ->orderBy('first_branch.id')
                    ->limit(1),
                'fallback_branch_name' => DB::table('branches as first_branch_name')
                    ->select('first_branch_name.name')
                    ->whereColumn('first_branch_name.shop_owner_id', 'products.shop_owner_id')
                    ->where('first_branch_name.is_active', true)
                    ->orderBy('first_branch_name.id')
                    ->limit(1),
            ])
            ->select([
                'products.id as product_id',
                'products.shop_owner_id',
                'shop_owners.shop_name',
                'products.name as product_name',
                'products.sku',
                'products.branch_id as source_branch_id',
                'source_branch_by_id.name as source_branch_name',
                'source_branches.id as valid_branch_id',
                'products.current_stock as original_stock',
            ])
            ->orderBy('products.id');
    }

    private function classify(object $product, bool $hasLog): array
    {
        $result = [
            'product_id' => $product->product_id,
            'shop_owner_id' => $product->shop_owner_id,
            'shop_name' => $product->shop_name,
            'product_name' => $product->product_name,
            'sku' => $product->sku,
            'source_branch_id' => $product->source_branch_id,
            'source_branch_name' => $product->source_branch_name,
            'destination_branch_id' => null,
            'destination_branch_name' => null,
            'original_stock' => $product->original_stock,
            'status' => null,
            'reason_code' => null,
            'reason' => null,
            'action' => 'would record',
        ];

        $log = $hasLog
            ? DB::table('stock_reconciliation_log')->where('product_id', $product->product_id)->first()
            : null;

        if ($log) {
            $result['shop_owner_id'] = $log->shop_owner_id;
            $result['shop_name'] = $log->shop_name_snapshot;
            $result['product_name'] = $log->product_name_snapshot;
            $result['sku'] = $log->sku_snapshot;
            $result['source_branch_id'] = $log->source_branch_id;
            $result['source_branch_name'] = $log->source_branch_name_snapshot;
            $result['destination_branch_id'] = $log->destination_branch_id;
            $result['destination_branch_name'] = $log->destination_branch_name_snapshot;
            $result['original_stock'] = $log->original_stock;
            $result['status'] = $log->status;
            $result['reason_code'] = $log->reason_code;
            $result['reason'] = $log->reason;

            if (! in_array($log->status, ['migrated', 'unresolved', 'zero_balance'], true)) {
                return $this->conflict($result, 'Unknown reconciliation status.');
            }

            if ((int) $product->shop_owner_id !== (int) $log->shop_owner_id) {
                return $this->conflict($result, 'Product ownership changed since reconciliation.');
            }

            if ((int) $product->source_branch_id !== (int) $log->source_branch_id
                || $this->decimal($product->original_stock) !== $this->decimal($log->original_stock)) {
                return $this->conflict($result, 'Product branch or source quantity changed since reconciliation.');
            }

            if ($log->status === 'migrated') {
                $destinationStillValid = match ($log->reason_code) {
                    'migrated' => $product->source_branch_id !== null
                        && (int) $product->valid_branch_id === (int) $log->destination_branch_id,
                    'default_first_branch' => $product->source_branch_id === null
                        && (int) $product->fallback_branch_id === (int) $log->destination_branch_id,
                    default => false,
                };

                if (! $destinationStillValid || $log->destination_branch_id === null) {
                    return $this->conflict($result, 'The recorded destination branch is not currently owned by the product shop.');
                }

                $stock = DB::table('product_stocks')
                    ->where('product_id', $log->product_id)
                    ->where('branch_id', $log->destination_branch_id)
                    ->first();

                if (! $stock || (int) $stock->shop_owner_id !== (int) $log->shop_owner_id
                    || $this->decimal($stock->current_stock) !== $this->decimal($log->original_stock)) {
                    return $this->conflict($result, 'The recorded stock row is missing or no longer matches the migrated balance.');
                }

                $result['action'] = 'already migrated';
            } elseif ($log->status === 'unresolved') {
                $hasTrackedStock = DB::table('product_stocks')->where('product_id', $product->product_id)->exists();
                if ($log->reason_code === 'missing_branch'
                    && $product->source_branch_id === null
                    && $product->fallback_branch_id !== null
                    && $this->decimal($product->original_stock) !== '0.0000'
                    && ! $hasTrackedStock) {
                    $result['status'] = 'migrated';
                    $result['reason_code'] = 'default_first_branch';
                    $result['destination_branch_id'] = $product->fallback_branch_id;
                    $result['destination_branch_name'] = $product->fallback_branch_name;
                    $result['reason'] = 'Legacy shop-level stock assigned to the first active branch after provisioning; product definition remains shared.';
                    $result['action'] = 'would record';

                    return $result;
                }

                if ($log->reason_code === 'missing_branch' && $hasTrackedStock) {
                    return $this->conflict($result, 'Tracked stock appeared after the missing-branch audit; reconcile it before retrying.');
                }

                $branchIsValid = $product->valid_branch_id !== null;
                $stillUnresolved = match ($log->reason_code) {
                    'missing_branch' => $product->source_branch_id === null,
                    'invalid_branch' => $product->source_branch_id !== null && ! $branchIsValid,
                    'existing_stock' => $branchIsValid,
                    default => false,
                };

                if (! $stillUnresolved || $log->destination_branch_id !== null) {
                    return $this->conflict($result, 'The unresolved reason or branch attribution changed; manual review is required.');
                }

                $result['action'] = 'preserved unresolved';
            } else {
                if (DB::table('product_stocks')->where('product_id', $product->product_id)->exists()) {
                    return $this->conflict($result, 'A tracked branch stock row exists for a product recorded as zero balance. Reconcile it manually.');
                }

                $zeroOutcomeStillValid = match ($log->reason_code) {
                    'zero_balance_valid_branch' => (int) $product->valid_branch_id === (int) $log->source_branch_id,
                    'zero_balance_missing_branch' => $product->source_branch_id === null,
                    'zero_balance_invalid_branch' => $product->source_branch_id !== null && $product->valid_branch_id === null,
                    default => false,
                };

                if (! $zeroOutcomeStillValid || $this->decimal($product->original_stock) !== '0.0000'
                    || $log->destination_branch_id !== null) {
                    return $this->conflict($result, 'The product is no longer eligible for its zero-balance outcome.');
                }

                $result['action'] = 'preserved zero balance';
            }

            return $result;
        }

        $stock = $product->original_stock;
        $existingStocks = DB::table('product_stocks')->where('product_id', $product->product_id)->get();

        if ($this->decimal($stock) === '0.0000') {
            if ($existingStocks->isNotEmpty()) {
                return $this->conflict($result, 'A tracked branch stock row exists while legacy stock is zero. Reconcile it manually.');
            }

            $result['status'] = 'zero_balance';
            $result['reason_code'] = $product->valid_branch_id !== null
                ? 'zero_balance_valid_branch'
                : ($product->source_branch_id === null ? 'zero_balance_missing_branch' : 'zero_balance_invalid_branch');
            $result['reason'] = $product->valid_branch_id !== null
                ? 'Zero balance; no stock row is required.'
                : 'Zero balance; no valid source branch, and no stock row will be assigned.';

            return $result;
        }

        $destinationBranchId = $product->valid_branch_id;
        $destinationBranchName = $product->source_branch_name;
        $reasonCode = 'migrated';

        if ($product->source_branch_id === null && $product->fallback_branch_id !== null) {
            $destinationBranchId = $product->fallback_branch_id;
            $destinationBranchName = $product->fallback_branch_name;
            $reasonCode = 'default_first_branch';
        }

        if ($destinationBranchId === null) {
            $result['status'] = 'unresolved';
            $result['reason_code'] = $product->source_branch_id === null ? 'missing_branch' : 'invalid_branch';
            $result['reason'] = $product->source_branch_id === null
                ? 'No source branch attribution and the shop has no active first branch.'
                : 'Source branch does not exist or belongs to another shop.';

            return $result;
        }

        if ($existingStocks->isNotEmpty()) {
            $result['status'] = 'unresolved';
            $matching = $existingStocks->contains(fn (object $row): bool =>
                (int) $row->branch_id === (int) $destinationBranchId
                && (int) $row->shop_owner_id === (int) $product->shop_owner_id
                && $this->decimal($row->current_stock) === $this->decimal($stock)
            );
            $result['reason'] = $matching
                ? 'An equal stock row exists without a reconciliation record; verify its provenance before migration.'
                : 'Existing branch stock has no reconciliation record; verify it before migration.';
            $result['reason_code'] = 'existing_stock';

            return $result;
        }

        $result['status'] = 'migrated';
        $result['reason_code'] = $reasonCode;
        $result['destination_branch_id'] = $destinationBranchId;
        $result['destination_branch_name'] = $destinationBranchName;
        $result['reason'] = $reasonCode === 'default_first_branch'
            ? 'Legacy shop-level stock assigned to the first active branch; product definition remains shared.'
            : 'Valid source branch and no existing branch stock row.';

        return $result;
    }

    private function lockSourceBranch(object $product): void
    {
        if ($product->source_branch_id === null) {
            $product->valid_branch_id = null;
            $product->source_branch_name = null;

            $fallbackBranch = DB::table('branches')
                ->where('shop_owner_id', $product->shop_owner_id)
                ->where('is_active', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();
            $product->fallback_branch_id = $fallbackBranch?->id;
            $product->fallback_branch_name = $fallbackBranch?->name;

            return;
        }

        $product->fallback_branch_id = null;
        $product->fallback_branch_name = null;

        $branch = DB::table('branches')
            ->where('id', $product->source_branch_id)
            ->lockForUpdate()
            ->first();

        $product->source_branch_name = $branch?->name;
        $product->valid_branch_id = $branch && (int) $branch->shop_owner_id === (int) $product->shop_owner_id
            ? $branch->id
            : null;
    }

    private function conflict(array $result, string $reason): array
    {
        $result['status'] = 'conflict';
        $result['reason'] = $reason;
        $result['action'] = 'manual review required';

        return $result;
    }

    private function decimal(mixed $value): string
    {
        $value = trim((string) $value);
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';
        $fraction = str_pad(substr($fraction, 0, 4), 4, '0');
        $zero = $whole === '0' && $fraction === '0000';

        return ($negative && ! $zero ? '-' : '').$whole.'.'.$fraction;
    }

    public function logColumns(): array
    {
        return [
            'product_id', 'shop_owner_id', 'shop_name_snapshot', 'product_name_snapshot',
            'sku_snapshot', 'source_branch_id', 'source_branch_name_snapshot',
            'destination_branch_id', 'destination_branch_name_snapshot', 'original_stock',
            'status', 'reason_code', 'reason', 'created_at', 'updated_at',
        ];
    }
}
