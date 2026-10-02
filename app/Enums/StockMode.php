<?php

namespace App\Enums;

enum StockMode: string
{
    case Own = 'own';
    case FromVariants = 'from_variants';
    case FromComponents = 'from_components';
}
