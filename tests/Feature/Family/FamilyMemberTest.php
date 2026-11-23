<?php

use App\Models\FamilyMember;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
});

it('lists only the owner family members', function () {
    FamilyMember::factory()->count(2)->create(['user_id' => $this->user->id]);
    FamilyMember::factory()->create(['user_id' => $this->otherUser->id]);

    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/me/family-members')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.page', 1)
        ->assertJsonPath('meta.per_page', 15);
});

it('caps per_page at fifty', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/me/family-members?per_page=500')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 50);
});

it('creates a family member', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/me/family-members', [
        'name' => 'Omar',
        'relation' => 'child',
        'date_of_birth' => '2015-06-01',
        'gender' => 'male',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Omar')
        ->assertJsonPath('data.relation', 'child');

    expect($this->user->familyMembers()->count())->toBe(1);
});

it('validates the family member payload', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/me/family-members', [
        'name' => 'Omar',
        'relation' => 'cousin',
        'date_of_birth' => now()->addYear()->toDateString(),
        'gender' => 'unknown',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['relation', 'date_of_birth', 'gender']);
});

it('shows an owned family member', function () {
    $member = FamilyMember::factory()->create(['user_id' => $this->user->id]);
    Sanctum::actingAs($this->user);

    $this->getJson("/api/v1/me/family-members/{$member->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $member->id);
});

it('returns 404 for another user family member', function () {
    $foreign = FamilyMember::factory()->create(['user_id' => $this->otherUser->id]);
    Sanctum::actingAs($this->user);

    $this->getJson("/api/v1/me/family-members/{$foreign->id}")
        ->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND');

    $this->patchJson("/api/v1/me/family-members/{$foreign->id}", ['name' => 'Hacked'])
        ->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND');

    $this->deleteJson("/api/v1/me/family-members/{$foreign->id}")
        ->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND');

    expect($foreign->refresh()->name)->not->toBe('Hacked');
});

it('returns 404 for a missing family member', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/me/family-members/999999')->assertNotFound();
});

it('updates an owned family member', function () {
    $member = FamilyMember::factory()->create(['user_id' => $this->user->id]);
    Sanctum::actingAs($this->user);

    $this->patchJson("/api/v1/me/family-members/{$member->id}", ['name' => 'Updated'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated');

    expect($member->refresh()->name)->toBe('Updated');
});

it('soft deletes an owned family member', function () {
    $member = FamilyMember::factory()->create(['user_id' => $this->user->id]);
    Sanctum::actingAs($this->user);

    $this->deleteJson("/api/v1/me/family-members/{$member->id}")->assertNoContent();

    $this->assertSoftDeleted('family_members', ['id' => $member->id]);
    $this->getJson('/api/v1/me/family-members')->assertJsonCount(0, 'data');
});

it('requires authentication', function () {
    $this->getJson('/api/v1/me/family-members')->assertUnauthorized();
});
