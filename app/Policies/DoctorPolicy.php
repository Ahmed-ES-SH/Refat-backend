<?php

namespace App\Policies;

use App\Models\Doctor;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DoctorPolicy
{
    /**
     * Determine whether the user can view any doctor.
     */
    public function viewAny(?User $user): Response|bool
    {
        if ($user?->role->isAdmin()) {
            return true;
        }

        if ($user?->role->isCenterStaff()) {
            return true;
        }

        return Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can view the doctor.
     */
    public function view(?User $user, Doctor $doctor): Response|bool
    {
        if ($user?->role->isAdmin()) {
            return true;
        }

        if ($user?->role->isCenterStaff() && $user->center_id === $doctor->center_id) {
            return true;
        }

        return Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can create a doctor.
     */
    public function create(?User $user): Response|bool
    {
        if ($user?->role->isAdmin() || $user?->role->isCenterStaff()) {
            return true;
        }

        return Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can update the doctor.
     */
    public function update(?User $user, Doctor $doctor): Response|bool
    {
        if ($user?->role->isAdmin()) {
            return true;
        }

        if ($user?->role->isCenterStaff() && $user->center_id === $doctor->center_id) {
            return true;
        }

        return Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can delete the doctor.
     */
    public function delete(?User $user, Doctor $doctor): Response|bool
    {
        if ($user?->role->isAdmin()) {
            return true;
        }

        if ($user?->role->isCenterStaff() && $user->center_id === $doctor->center_id) {
            return true;
        }

        return Response::denyAsNotFound();
    }
}
