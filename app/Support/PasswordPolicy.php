<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * The password rules of the application, in one place.
 *
 * A patient account only needs a usable password, but a staff account can
 * moderate a center and read patient data, so it has to be stronger.
 */
final class PasswordPolicy
{
    public static function forPatients(): Password
    {
        return Password::min(8);
    }

    public static function forStaff(): Password
    {
        return Password::min(8)->mixedCase()->numbers()->symbols();
    }
}
