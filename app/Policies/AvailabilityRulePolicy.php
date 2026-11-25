<?php

namespace App\Policies;

use App\Models\AvailabilityRule;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AvailabilityRulePolicy
{
    /**
     * Determine whether the user can view the rule.
     */
    public function view(?User $user, AvailabilityRule $rule): Response|bool
    {
        return app(DoctorPolicy::class)->view($user, $this->doctorOf($rule));
    }

    /**
     * Determine whether the user can update the rule.
     */
    public function update(?User $user, AvailabilityRule $rule): Response|bool
    {
        return app(DoctorPolicy::class)->update($user, $this->doctorOf($rule));
    }

    /**
     * Determine whether the user can delete the rule.
     */
    public function delete(?User $user, AvailabilityRule $rule): Response|bool
    {
        return app(DoctorPolicy::class)->delete($user, $this->doctorOf($rule));
    }

    /**
     * A rule carries only its doctor, and the center lives on the doctor, so
     * the doctor must be loaded to apply the center-isolation rule.
     */
    private function doctorOf(AvailabilityRule $rule): Doctor
    {
        return Doctor::findOrFail($rule->doctor_id);
    }
}
