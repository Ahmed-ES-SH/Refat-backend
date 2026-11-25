<?php

use App\Models\Center;
use App\Models\User;
use App\Policies\CenterPolicy;
use Illuminate\Auth\Access\Response;

/**
 * A policy method may answer with a bool or with a Response; both are read
 * through the same assertion.
 */
beforeEach(function () {
    $this->allows = fn (mixed $result): bool => $result instanceof Response
        ? $result->allowed()
        : (bool) $result;

    $this->policy = app(CenterPolicy::class);
});

it('lets a guest view an active center only', function () {
    $active = Center::factory()->active()->create();
    $suspended = Center::factory()->suspended()->create();

    expect(($this->allows)($this->policy->view(null, $active)))->toBeTrue()
        ->and(($this->allows)($this->policy->view(null, $suspended)))->toBeFalse();
});

it('lets an admin view and moderate any center', function () {
    $admin = User::factory()->admin()->create();
    $center = Center::factory()->create();

    expect(($this->allows)($this->policy->view($admin, $center)))->toBeTrue()
        ->and(($this->allows)($this->policy->approve($admin, $center)))->toBeTrue()
        ->and(($this->allows)($this->policy->suspend($admin, $center)))->toBeTrue();
});

it('lets a patient view an active center but not moderate it', function () {
    $patient = User::factory()->create();
    $center = Center::factory()->active()->create();

    expect(($this->allows)($this->policy->view($patient, $center)))->toBeTrue()
        ->and(($this->allows)($this->policy->approve($patient, $center)))->toBeFalse()
        ->and(($this->allows)($this->policy->suspend($patient, $center)))->toBeFalse()
        ->and(($this->allows)($this->policy->update($patient, $center)))->toBeFalse();
});

it('lets center staff moderate only their own center', function () {
    $center = Center::factory()->active()->create();
    $other = Center::factory()->active()->create();

    $staff = User::factory()->centerStaff($center)->create();

    expect(($this->allows)($this->policy->update($staff, $center)))->toBeTrue()
        ->and(($this->allows)($this->policy->update($staff, $other)))->toBeFalse();
});
