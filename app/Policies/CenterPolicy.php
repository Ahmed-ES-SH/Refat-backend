<?php

namespace App\Policies;

use App\Models\Center;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CenterPolicy
{
    /**
     * Determine whether the user can view the center.
     */
    public function view(?User $user, Center $center): Response|bool
    {
        if ($user?->role->isAdmin()) {
            return true;
        }

        if ($user?->role->isCenterStaff() && $user->center_id === $center->id) {
            return true;
        }

        // A center that is not active must not even be confirmed to exist.
        return $center->status->isActive()
            ? true
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can approve the center.
     */
    public function approve(User $user, Center $center): bool
    {
        return $user->role->isAdmin();
    }

    /**
     * Determine whether the user can suspend the center.
     */
    public function suspend(User $user, Center $center): bool
    {
        return $user->role->isAdmin();
    }

    /**
     * Determine whether the user can update the center.
     */
    public function update(User $user, Center $center): Response|bool
    {
        if (! $user->role->isCenterStaff()) {
            return false;
        }

        // Staff of another center must not learn that the center exists.
        return $user->center_id === $center->id
            ? true
            : Response::denyAsNotFound();
    }
}
