<?php

namespace App\Exceptions;

/** Thrown in price_range mode when the requested price is below the product's configured floor. */
class PriceBelowMinimumException extends \RuntimeException
{
}