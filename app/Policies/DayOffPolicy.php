<?php

namespace App\Policies;

use App\Models\DayOff;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DayOffPolicy
{
    /**
     * Determine whether the user can view the day off.
     */
    public function view(?User $user, DayOff $dayOff): Response|bool
    {
        return app(DoctorPolicy::class)->view($user, $this->doctorOf($dayOff));
    }

    /**
     * Determine whether the user can update the day off.
     */
    public function update(?User $user, DayOff $dayOff): Response|bool
    {
        return app(DoctorPolicy::class)->update($user, $this->doctorOf($dayOff));
    }

    /**
     * Determine whether the user can delete the day off.
     */
    public function delete(?User $user, DayOff $dayOff): Response|bool
    {
        return app(DoctorPolicy::class)->delete($user, $this->doctorOf($dayOff));
    }

    /**
     * A day off carries only its doctor, and the center lives on the doctor,
     * so the doctor must be loaded to apply the center-isolation rule.
     */
    private function doctorOf(DayOff $dayOff): Doctor
    {
        return Doctor::findOrFail($dayOff->doctor_id);
    }
}
