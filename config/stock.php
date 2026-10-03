<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Require variant on variant products
    |--------------------------------------------------------------------------
    | When true, every inventory movement and POS sale on a "from_variants"
    | product must specify a variant_id. Keep false until PosPage sends
    | variant_id — P4 ships with this off so existing POS flows keep working.
    */
    'require_variant' => env('STOCK_REQUIRE_VARIANT', false),
];
