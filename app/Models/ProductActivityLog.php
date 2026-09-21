<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductActivityLog extends Model
{
    protected $fillable = ['product_id', 'user_id', 'action', 'field', 'old_value', 'new_value', 'description'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
