<?php

namespace App\Actions;

use App\Enums\CenterStatus;
use App\Enums\Locale;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Center;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RegisterCenterWithStaff
{
    /**
     * Create a center in the pending state together with its first staff
     * account, in one transaction.
     *
     * The staff account is created **unverified** and receives the standard
     * verification email: this is self-registration, so the address is chosen
     * by the registrant and ownership has not been proven yet.
     *
     * @param  array<string, mixed>  $payload  The validated registration payload.
     */
    public function handle(array $payload): Center
    {
        [$center, $staff] = DB::transaction(function () use ($payload): array {
            $staffAttributes = Arr::only($payload, ['staff_name', 'staff_email', 'staff_password']);

            $center = Center::create([
                ...Arr::except($payload, array_keys($staffAttributes)),
                'status' => CenterStatus::Pending->value,
            ]);

            $staff = User::create([
                'name' => $staffAttributes['staff_name'],
                'email' => $staffAttributes['staff_email'],
                'password' => $staffAttributes['staff_password'],
                'role' => UserRole::CenterStaff,
                'status' => UserStatus::Active,
                'center_id' => $center->id,
                'email_verified_at' => null,
                'locale' => Locale::Ar->value,
            ]);

            return [$center->load('staff'), $staff];
        });

        // Sent after the commit: the signed link only resolves once the account
        // exists, and the registrant must prove ownership of `staff_email`
        // before the account counts as verified.
        $staff->sendEmailVerificationNotification();

        return $center;
    }
}
