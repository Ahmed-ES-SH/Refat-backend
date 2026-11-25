<?php

namespace App\Enums;

enum CenterStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';

    /**
     * Check if the status allows public directory access.
     */
    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
