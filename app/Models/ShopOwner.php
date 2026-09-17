<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class ShopOwner extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'user_id',
        'shop_name',
        'location',
        'address',
        'phone',
        'business_type',
        'tax_id',
        'description',
        'website',
        'plan_id',
        'staff_count',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            // no special casts needed
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function staff()
    {
        return $this->hasMany(\App\Models\Staff::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function hasModule(string $slug): bool
    {
        return $this->plan && $this->plan->hasModule($slug);
    }
}
