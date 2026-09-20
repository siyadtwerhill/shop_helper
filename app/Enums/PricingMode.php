<?php

namespace App\Enums;

enum PricingMode: string
{
    case Fixed = 'fixed';
    case Negotiable = 'negotiable';
    case PriceRange = 'price_range';
    case Wholesale = 'wholesale';
}