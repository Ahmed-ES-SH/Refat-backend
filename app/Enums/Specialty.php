<?php

namespace App\Enums;

enum Specialty: string
{
    case GeneralMedicine = 'general-medicine';
    case Pediatrics = 'pediatrics';
    case Dentistry = 'dentistry';
    case Dermatology = 'dermatology';
    case Gynecology = 'gynecology';
    case Orthopedics = 'orthopedics';
    case Cardiology = 'cardiology';
    case Ophthalmology = 'ophthalmology';
    case Ent = 'ent';
    case Psychiatry = 'psychiatry';
    case Nutrition = 'nutrition';
    case Physiotherapy = 'physiotherapy';

    /**
     * Get all specialty values as an array.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
