<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Database\Factories\FieldDefinitionFactory;

class FieldDefinition extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory()
    {
        return FieldDefinitionFactory::new();
    }

    protected $fillable = [
        'shop_owner_id',
        'key',
        'label',
        'type',
        'options',
        'validation',
    ];

    protected $casts = [
        'options' => 'array',
        'validation' => 'array',
    ];

    public function shopOwner(): BelongsTo
    {
        return $this->belongsTo(ShopOwner::class);
    }

    public function blueprintFields(): HasMany
    {
        return $this->hasMany(BlueprintField::class);
    }
}
