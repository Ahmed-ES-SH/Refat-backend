<?php

use App\Enums\Specialty;
use App\Models\Center;
use App\Models\Doctor;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->center = Center::factory()->active()->create();
    $this->staff = User::factory()->centerStaff($this->center)->create();
    $this->otherCenter = Center::factory()->active()->create();
    $this->otherStaff = User::factory()->centerStaff($this->otherCenter)->create();
    $this->admin = User::factory()->admin()->create();
});

it('lets center staff create a doctor for their own center', function () {
    Sanctum::actingAs($this->staff);

    $this->postJson('/api/v1/center/doctors', [
        'name' => 'Dr. Jane Doe',
        'specialty' => Specialty::Cardiology->value,
        'bio' => 'A great cardiologist.',
        'is_active' => true,
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'Dr. Jane Doe')
        ->assertJsonPath('data.specialty', Specialty::Cardiology->value)
        ->assertJsonPath('data.is_active', true);

    $this->assertDatabaseHas('doctors', [
        'name' => 'Dr. Jane Doe',
        'center_id' => $this->center->id,
        'specialty' => Specialty::Cardiology->value,
    ]);
});

it('auto-sets center_id from the authenticated staff user', function () {
    Sanctum::actingAs($this->staff);

    $this->postJson('/api/v1/center/doctors', [
        'name' => 'Dr. John Smith',
        'specialty' => Specialty::Pediatrics->value,
    ])->assertStatus(201);

    $doctor = Doctor::where('name', 'Dr. John Smith')->first();
    expect($doctor->center_id)->toBe($this->center->id);
});

it('rejects an invalid specialty', function () {
    Sanctum::actingAs($this->staff);

    $this->postJson('/api/v1/center/doctors', [
        'name' => 'Dr. Bad',
        'specialty' => 'not-a-real-specialty',
    ])->assertUnprocessable()->assertJsonValidationErrors('specialty');
});

it('lets center staff list their own doctors only', function () {
    $myDoctor = Doctor::factory()->create(['center_id' => $this->center->id]);
    $otherDoctor = Doctor::factory()->create(['center_id' => $this->otherCenter->id]);

    Sanctum::actingAs($this->staff);

    $this->getJson('/api/v1/center/doctors')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $myDoctor->id);
});

it('lets an admin list all doctors', function () {
    Doctor::factory()->create(['center_id' => $this->center->id]);
    Doctor::factory()->create(['center_id' => $this->otherCenter->id]);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/center/doctors')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('lets center staff update a doctor in their center', function () {
    $doctor = Doctor::factory()->create(['center_id' => $this->center->id]);

    Sanctum::actingAs($this->staff);

    $this->patchJson("/api/v1/center/doctors/{$doctor->id}", [
        'name' => 'Dr. Updated',
        'bio' => 'New bio',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Dr. Updated')
        ->assertJsonPath('data.bio', 'New bio');

    expect($doctor->refresh()->name)->toBe('Dr. Updated');
});

it('deactivates a doctor instead of deleting it', function () {
    $doctor = Doctor::factory()->create(['center_id' => $this->center->id]);

    Sanctum::actingAs($this->staff);

    $this->deleteJson("/api/v1/center/doctors/{$doctor->id}")->assertStatus(204);

    expect($doctor->refresh()->is_active)->toBeFalse();
});

it('returns 404 for a doctor in another center', function () {
    $doctor = Doctor::factory()->create(['center_id' => $this->otherCenter->id]);

    Sanctum::actingAs($this->staff);

    $this->getJson("/api/v1/center/doctors/{$doctor->id}")->assertNotFound();
    $this->patchJson("/api/v1/center/doctors/{$doctor->id}", ['name' => 'Hacked'])->assertNotFound();
    $this->deleteJson("/api/v1/center/doctors/{$doctor->id}")->assertNotFound();
});

it('requires authentication', function () {
    $this->getJson('/api/v1/center/doctors')->assertUnauthorized();
    $this->postJson('/api/v1/center/doctors', [])->assertUnauthorized();
});

it('rejects a doctor name longer than 120 characters', function () {
    Sanctum::actingAs($this->staff);

    $this->postJson('/api/v1/center/doctors', [
        'name' => str_repeat('a', 121),
        'specialty' => Specialty::Cardiology->value,
    ])->assertUnprocessable()->assertJsonValidationErrors('name');
});
