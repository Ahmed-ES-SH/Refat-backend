<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * The weekday of an availability rule, stored as a number.
 *
 * The convention is the one of the `availability_rules.weekday` column:
 * 0 = Sunday … 6 = Saturday.
 */
enum Weekday: int
{
    case Sunday = 0;
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;

    /**
     * The weekday of a date, in the same convention.
     */
    public static function fromDate(CarbonInterface $date): self
    {
        return self::from((int) $date->format('w'));
    }
}
