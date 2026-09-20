<?php

namespace App\Exceptions;

/** Thrown when a price falls below cost_price + min_margin_percent and the user lacks approval permission. */
class MinimumMarginViolationException extends \RuntimeException
{
}