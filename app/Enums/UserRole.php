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

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    public function isCenterStaff(): bool
    {
        return $this === self::CenterStaff;
    }

    public function isPatient(): bool
    {
        return $this === self::Patient;
    }
}
