<?php

namespace App\Enums;

enum UserRole: string
{
    case Patient = 'patient';
    case CenterStaff = 'center_staff';
    case Admin = 'admin';

    /**
     * Permissions granted to this role.
     *
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return config("permissions.roles.{$this->value}", []);
    }
}
