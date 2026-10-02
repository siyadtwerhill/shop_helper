<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Database\Factories\BlueprintFieldFactory;

class BlueprintField extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return BlueprintFieldFactory::new();
    }

    protected $fillable = [
        'blueprint_id',
        'field_definition_id',
        'section',
        'sort_order',
        'required',
        'show_in_list',
        'show_in_pos',
        'show_on_label',
        'is_variant_axis',
        'is_filterable',
        'is_searchable',
        'hidden',
    ];

    protected $casts = [
        'required' => 'boolean',
        'show_in_list' => 'boolean',
        'show_in_pos' => 'boolean',
        'show_on_label' => 'boolean',
        'is_variant_axis' => 'boolean',
        'is_filterable' => 'boolean',
        'is_searchable' => 'boolean',
        'hidden' => 'boolean',
    ];

    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    public function fieldDefinition(): BelongsTo
    {
        return $this->belongsTo(FieldDefinition::class);
    }
}
