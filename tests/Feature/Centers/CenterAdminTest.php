<?php

use App\Models\Center;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('approves a pending center', function () {
    $admin = User::factory()->admin()->create();
    $center = Center::factory()->create(['status' => 'pending']);

    Sanctum::actingAs($admin);

    $this->postJson("/api/v1/admin/centers/{$center->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonStructure(['data' => ['id', 'name', 'status', 'approved_at', 'staff']]);

    expect($center->refresh()->status->value)->toBe('active')
        ->and($center->approved_at)->not->toBeNull();
});

it('suspends an active center', function () {
    $admin = User::factory()->admin()->create();
    $center = Center::factory()->active()->create();

    Sanctum::actingAs($admin);

    $this->postJson("/api/v1/admin/centers/{$center->id}/suspend")
        ->assertOk()
        ->assertJsonPath('data.status', 'suspended');

    expect($center->refresh()->status->value)->toBe('suspended');
});

it('refuses to approve a center for a non admin', function () {
    $staff = User::factory()->centerStaff()->create();
    $center = Center::factory()->create(['status' => 'pending']);

    Sanctum::actingAs($staff);

    $this->postJson("/api/v1/admin/centers/{$center->id}/approve")
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    expect($center->refresh()->status->value)->toBe('pending');
});

it('refuses to approve a center for a guest', function () {
    $center = Center::factory()->create(['status' => 'pending']);

    $this->postJson("/api/v1/admin/centers/{$center->id}/approve")
        ->assertUnauthorized()
        ->assertJsonPath('code', 'UNAUTHENTICATED');
});
