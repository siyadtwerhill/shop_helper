<?php

namespace App\Services;

use App\Models\Blueprint;
use App\Services\ProductSystemFields;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BlueprintValidationBuilder
{
    /**
     * Adjust base rules so hidden built-in fields are stripped and required ones are enforced.
     *
     * @param array $rules    The base validation rules array from ProductController.
     * @param bool  $partial  true on update (uses 'sometimes' instead of hard-required).
     * @param array $skipRequired Keys to treat as never-required even if the blueprint says so.
     */
    public function applySystemFields(Blueprint $bp, array $rules, bool $partial = false, array $skipRequired = []): array
    {
        foreach (ProductSystemFields::resolve($bp) as $key => $cfg) {
            if (! array_key_exists($key, $rules)) continue;

            if ($cfg['hidden']) {
                unset($rules[$key]); // validate() then drops it from $data, so it is never saved
                continue;
            }

            if (! $cfg['required'] || in_array($key, $skipRequired, true)) continue;

            $r = is_array($rules[$key]) ? $rules[$key] : explode('|', $rules[$key]);
            $r = array_values(array_filter($r, fn ($x) => ! in_array($x, ['nullable', 'sometimes', 'required'], true)));

            // On update the image already exists; only validate it when one is explicitly sent.
            $rules[$key] = $key === 'image'
                ? ($partial ? array_merge(['sometimes'], $r) : array_merge(['required'], $r))
                : array_merge($partial ? ['sometimes', 'required'] : ['required'], $r);
        }

        return $rules;
    }

    /** Rules for the product's custom_fields, built from the blueprint (never from the client). */
    public function customFieldRules(Blueprint $bp, bool $partial = false, bool $relaxRequired = false): array
    {
        $rules = ['custom_fields' => ['nullable', 'array']];

        foreach ($this->fieldsFor($bp) as $field) {
            $def = $field->fieldDefinition;
            $key = "custom_fields.{$def->key}";
            $limits = $def->validation ?? [];

            $r = $field->required && ! $relaxRequired
                ? array_filter([$partial ? 'sometimes' : null, 'required'])
                : ['nullable'];

            switch ($this->typeOf($def)) {
                case 'number':
                case 'decimal':
                    $r[] = 'numeric';
                    if (isset($limits['min'])) $r[] = 'min:' . $limits['min'];
                    if (isset($limits['max'])) $r[] = 'max:' . $limits['max'];
                    break;
                case 'select':
                    $r[] = Rule::in($this->optionValues($def->options ?? []));
                    break;
                case 'multi_select':
                    $r[] = 'array';
                    $rules["$key.*"] = [Rule::in($this->optionValues($def->options ?? []))];
                    break;
                case 'date':
                    $r[] = 'date';
                    break;
                case 'boolean':
                case 'checkbox':
                    $r[] = 'boolean';
                    break;
                case 'json':
                    $r[] = 'array';
                    break;
                case 'textarea':
                    $r[] = 'string';
                    $r[] = 'max:' . ($limits['max_length'] ?? 5000);
                    break;
                case 'number_unit':
                    $r[] = 'array';
                    $rules["$key.value"] = ['required_with:' . $key, 'numeric'];
                    $rules["$key.unit"] = ['nullable', 'string', 'max:20'];
                    break;
                default:
                    $r[] = 'string';
                    $r[] = 'max:' . ($limits['max_length'] ?? 255);
            }

            $rules[$key] = $r;
        }

        return $rules;
    }

    /** validate() keeps the whole custom_fields array, so unknown keys must be refused explicitly. */
    public function assertKnownKeys(Blueprint $bp, array $input): void
    {
        $allowed = $this->fieldsFor($bp)->map(fn ($f) => $f->fieldDefinition->key)->all();
        $unknown = array_diff(array_keys($input), $allowed);

        if ($unknown) {
            throw ValidationException::withMessages([
                'custom_fields' => ['Unknown fields: ' . implode(', ', $unknown)],
            ]);
        }
    }

    private function fieldsFor(Blueprint $bp): Collection
    {
        return $bp->loadMissing('fields.fieldDefinition')->fields
            ->filter(fn ($f) => ! $f->hidden && ! $f->is_variant_axis && $f->fieldDefinition);
    }

    private function typeOf($def): string
    {
        return $def->type instanceof \BackedEnum ? $def->type->value : (string) $def->type;
    }

    /** Supports both plain strings (current presets) and {value,label,active} options. */
    private function optionValues(array $options): array
    {
        return collect($options)
            ->map(function ($o) {
                if (! is_array($o)) return $o;
                return ($o['active'] ?? true) ? ($o['value'] ?? null) : null;
            })
            ->filter(fn ($v) => $v !== null)
            ->values()->all();
    }
}
