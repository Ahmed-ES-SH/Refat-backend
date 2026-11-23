<?php

namespace App\Enums;

enum Locale: string
{
    case Ar = 'ar';
    case En = 'en';

    /**
     * Resolve a locale from an Accept-Language header value.
     */
    public static function fromHeader(?string $header): ?self
    {
        if ($header === null || $header === '') {
            return null;
        }

        foreach (explode(',', $header) as $part) {
            $code = strtolower(substr(trim(explode(';', $part)[0]), 0, 2));

            if ($locale = self::tryFrom($code)) {
                return $locale;
            }
        }

        return null;
    }
}
