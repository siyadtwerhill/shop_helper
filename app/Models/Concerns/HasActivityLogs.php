<?php

namespace App\Models\Concerns;

use App\Models\ProductActivityLog;

/** Add to Product.php: use App\Models\Concerns\HasActivityLogs; */
trait HasActivityLogs
{
    public function activityLogs()
    {
        return $this->hasMany(ProductActivityLog::class)->latest();
    }
}
