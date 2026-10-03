# Blueprint Architecture - Key Contract

## **Capabilities** (Boolean flags in blueprint.capabilities)
- `variants` - Product supports variants
- `bundles` - Product supports bundles
- `batch_expiry` - Track batch numbers and expiry dates
- `serial_numbers` - Track serial numbers
- `decimal_quantities` - Allow decimal quantities
- `multiple_units` - Product can have multiple units

## **Pricing Policy** (Configuration in blueprint.pricing_policy)
- `allowed_modes` - Array of allowed pricing modes: `['fixed', 'negotiable', 'price_range', 'wholesale']`
- `default_mode` - Default pricing mode: must be in `allowed_modes`
- `min_margin_percent` - Minimum margin percent: 0-100
- `cost_required` - Whether cost price is required: boolean
- `block_below_margin` - Block sales below minimum margin: boolean
- `price_per_variant` - Variant products can have independent prices: boolean
- `pos_price_override` - Who can override price at POS: boolean

## **Unit Policy** (Configuration in blueprint.unit_policy)
- `default_base_unit_id` - ID of default base unit (nullable)
- `allowed_unit_ids` - Array of allowed unit IDs
- `price_per_unit` - Set different prices per unit: boolean
- `barcode_per_unit` - Generate barcode per unit: boolean
- `buy_sell_different_units` - Purchase and sell in different units: boolean

## **Field Types** (enum values)
- `text`
- `textarea`
- `number`
- `decimal`
- `select`
- `multi_select`
- `checkbox`
- `date`
- `boolean`
- `json`

## **Stock Modes** (enum values)
- `own` - Product owns its own stock (neutral products)
- `from_variants` - Stock derived from variants (variant products)
- `from_components` - Stock derived from components (bundle products)

## **Column Naming**
- `products.custom_fields` - NOT `attributes` (to avoid Eloquent internal collision)
- `product_variants.attributes_hash` - Hash for variant uniqueness
- `inventory_movements.variant_id` - FK to product_variants for variant-specific movements
