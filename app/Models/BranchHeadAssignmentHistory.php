<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchHeadAssignmentHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_owner_id',
        'branch_id',
        'previous_head_staff_id',
        'new_head_staff_id',
        'assigned_by_user_id',
        'action',
        'notes',
    ];

    protected $casts = [
        'action' => 'string',
    ];

    public function shopOwner(): BelongsTo
    {
        return $this->belongsTo(ShopOwner::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function previousHead(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'previous_head_staff_id');
    }

    public function newHead(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'new_head_staff_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    /**
     * Log a branch head assignment action.
     */
    public static function log(
        int $shopOwnerId,
        int $branchId,
        ?int $previousHeadStaffId,
        ?int $newHeadStaffId,
        int $assignedByUserId,
        string $action,
        ?string $notes = null
    ): self {
        return static::create([
            'shop_owner_id' => $shopOwnerId,
            'branch_id' => $branchId,
            'previous_head_staff_id' => $previousHeadStaffId,
            'new_head_staff_id' => $newHeadStaffId,
            'assigned_by_user_id' => $assignedByUserId,
            'action' => $action,
            'notes' => $notes,
        ]);
    }
}
