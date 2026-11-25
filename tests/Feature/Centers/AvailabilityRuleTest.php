<?php

use App\Models\AvailabilityRule;
use App\Models\Center;
use App\Models\Doctor;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->center = Center::factory()->active()->create();
    $this->staff = User::factory()->centerStaff($this->center)->create();
    $this->doctor = Doctor::factory()->create(['center_id' => $this->center->id]);

    $this->otherCenter = Center::factory()->active()->create();
    $this->otherStaff = User::factory()->centerStaff($this->otherCenter)->create();
    $this->otherDoctor = Doctor::factory()->create(['center_id' => $this->otherCenter->id]);

    $this->admin = User::factory()->admin()->create();
});

it('lets center staff create an availability rule for their own doctor', function () {
    Sanctum::actingAs($this->staff);

    $this->postJson("/api/v1/center/doctors/{$this->doctor->id}/availability-rules", [
        'weekday' => 1,
        'start_time' => '09:00',
        'end_time' => '17:00',
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.doctor_id', $this->doctor->id)
        ->assertJsonPath('data.weekday', 1)
        ->assertJsonPath('data.start_time', '09:00')
        ->assertJsonPath('data.end_time', '17:00')
        ->assertJsonPath('data.slot_minutes', 30)
        ->assertJsonPath('data.slots_count', 16);

    $this->assertDatabaseHas('availability_rules', [
        'doctor_id' => $this->doctor->id,
        'weekday' => 1,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'slot_minutes' => 30,
    ]);
});

it('lists the rules of a doctor ordered by weekday', function () {
    AvailabilityRule::factory()->create([
        'doctor_id' => $this->doctor->id,
        'weekday' => 6,
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
        'slot_minutes' => 30,
    ]);
    AvailabilityRule::factory()->create([
        'doctor_id' => $this->doctor->id,
        'weekday' => 1,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
        'slot_minutes' => 30,
    ]);

    Sanctum::actingAs($this->staff);

    $this->getJson("/api/v1/center/doctors/{$this->doctor->id}/availability-rules")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.weekday', 1)
        ->assertJsonPath('data.1.weekday', 6);
});

it('rejects a window that overlaps an existing one', function () {
    AvailabilityRule::factory()->create([
        'doctor_id' => $this->doctor->id,
        'weekday' => 1,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
        'slot_minutes' => 30,
    ]);

    Sanctum::actingAs($this->staff);

    $this->postJson("/api/v1/center/doctors/{$this->doctor->id}/availability-rules", [
        'weekday' => 1,
        'start_time' => '11:00',
        'end_time' => '13:00',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('start_time');
});

it('allows a window that starts when the previous one ends', function () {
    AvailabilityRule::factory()->create([
        'doctor_id' => $this->doctor->id,
        'weekday' => 1,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
        'slot_minutes' => 30,
    ]);

    Sanctum::actingAs($this->staff);

    $this->postJson("/api/v1/center/doctors/{$this->doctor->id}/availability-rules", [
        'weekday' => 1,
        'start_time' => '12:00',
        'end_time' => '14:00',
    ])->assertStatus(201);
});

it('allows the same window on a different weekday', function () {
    AvailabilityRule::factory()->create([
        'doctor_id' => $this->doctor->id,
        'weekday' => 1,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
        'slot_minutes' => 30,
    ]);

    Sanctum::actingAs($this->staff);

    $this->postJson("/api/v1/center/doctors/{$this->doctor->id}/availability-rules", [
        'weekday' => 3,
        'start_time' => '09:00',
        'end_time' => '12:00',
    ])->assertStatus(201);
});

it('rejects a window that ends at or before it starts', function () {
    Sanctum::actingAs($this->staff);

    $this->postJson("/api/v1/center/doctors/{$this->doctor->id}/availability-rules", [
        'weekday' => 1,
        'start_time' => '10:00',
        'end_time' => '09:00',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('end_time');

    $this->postJson("/api/v1/center/doctors/{$this->doctor->id}/availability-rules", [
        'weekday' => 1,
        'start_time' => '10:00',
        'end_time' => '10:00',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('end_time');
});

it('rejects a window that does not divide into whole slots', function () {
    Sanctum::actingAs($this->staff);

    $this->postJson("/api/v1/center/doctors/{$this->doctor->id}/availability-rules", [
        'weekday' => 1,
        'start_time' => '09:00',
        'end_time' => '10:00',
        'slot_minutes' => 45,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('end_time');
});

it('rejects a weekday outside 0 to 6', function () {
    Sanctum::actingAs($this->staff);

    $this->postJson("/api/v1/center/doctors/{$this->doctor->id}/availability-rules", [
        'weekday' => 7,
        'start_time' => '09:00',
        'end_time' => '12:00',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('weekday');
});

it('rejects malformed times', function () {
    Sanctum::actingAs($this->staff);

    $this->postJson("/api/v1/center/doctors/{$this->doctor->id}/availability-rules", [
        'weekday' => 1,
        'start_time' => 'nine o\'clock',
        'end_time' => '12:00',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('start_time');

    $this->postJson("/api/v1/center/doctors/{$this->doctor->id}/availability-rules", [
        'weekday' => 1,
        'start_time' => '25:00',
        'end_time' => '26:00',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('start_time');
});

it('lets center staff update a rule in their own center', function () {
    $rule = AvailabilityRule::factory()->create([
        'doctor_id' => $this->doctor->id,
        'weekday' => 1,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
        'slot_minutes' => 30,
    ]);

    Sanctum::actingAs($this->staff);

    $this->patchJson("/api/v1/center/availability-rules/{$rule->id}", [
        'start_time' => '10:00',
        'end_time' => '13:00',
    ])
        ->assertOk()
        ->assertJsonPath('data.start_time', '10:00')
        ->assertJsonPath('data.end_time', '13:00');

    $this->assertDatabaseHas('availability_rules', [
        'id' => $rule->id,
        'start_time' => '10:00:00',
        'end_time' => '13:00:00',
    ]);
});

it('does not count the rule itself as an overlap when updating', function () {
    $rule = AvailabilityRule::factory()->create([
        'doctor_id' => $this->doctor->id,
        'weekday' => 1,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
        'slot_minutes' => 30,
    ]);

    Sanctum::actingAs($this->staff);

    $this->patchJson("/api/v1/center/availability-rules/{$rule->id}", [
        'weekday' => 1,
        'start_time' => '09:00',
        'end_time' => '12:00',
    ])->assertOk();
});

it('checks the overlap against the weekday the rule is moving to', function () {
    AvailabilityRule::factory()->create([
        'doctor_id' => $this->doctor->id,
        'weekday' => 1,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
        'slot_minutes' => 30,
    ]);
    $rule = AvailabilityRule::factory()->create([
        'doctor_id' => $this->doctor->id,
        'weekday' => 2,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
        'slot_minutes' => 30,
    ]);

    Sanctum::actingAs($this->staff);

    $this->patchJson("/api/v1/center/availability-rules/{$rule->id}", [
        'weekday' => 1,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('start_time');
});

it('does not let an update move the rule to another doctor', function () {
    $rule = AvailabilityRule::factory()->create([
        'doctor_id' => $this->doctor->id,
        'weekday' => 1,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
        'slot_minutes' => 30,
    ]);

    Sanctum::actingAs($this->staff);

    $this->patchJson("/api/v1/center/availability-rules/{$rule->id}", [
        'doctor_id' => $this->otherDoctor->id,
    ])->assertOk();

    expect($rule->refresh()->doctor_id)->toBe($this->doctor->id);
});

it('lets center staff delete a rule of their own doctor', function () {
    $rule = AvailabilityRule::factory()->create(['doctor_id' => $this->doctor->id]);

    Sanctum::actingAs($this->staff);

    $this->deleteJson("/api/v1/center/availability-rules/{$rule->id}")->assertStatus(204);

    $this->assertDatabaseMissing('availability_rules', ['id' => $rule->id]);
});

it('returns 404 when another center touches a rule or a doctor', function () {
    $foreignRule = AvailabilityRule::factory()->create(['doctor_id' => $this->otherDoctor->id]);

    Sanctum::actingAs($this->staff);

    $this->getJson("/api/v1/center/doctors/{$this->otherDoctor->id}/availability-rules")->assertNotFound();

    $this->postJson("/api/v1/center/doctors/{$this->otherDoctor->id}/availability-rules", [
        'weekday' => 1,
        'start_time' => '09:00',
        'end_time' => '12:00',
    ])->assertNotFound();

    $this->getJson("/api/v1/center/availability-rules/{$foreignRule->id}")->assertNotFound();
    $this->patchJson("/api/v1/center/availability-rules/{$foreignRule->id}", [
        'start_time' => '10:00',
    ])->assertNotFound();
    $this->deleteJson("/api/v1/center/availability-rules/{$foreignRule->id}")->assertNotFound();
});

it('answers 404, not 422, for invalid input on a foreign rule', function () {
    $foreignRule = AvailabilityRule::factory()->create(['doctor_id' => $this->otherDoctor->id]);

    Sanctum::actingAs($this->staff);

    // Authorization runs before validation, so a 422 here would confirm the
    // rule exists in another center.
    $this->patchJson("/api/v1/center/availability-rules/{$foreignRule->id}", [
        'start_time' => 'not a time',
    ])->assertNotFound();

    $this->postJson("/api/v1/center/doctors/{$this->otherDoctor->id}/availability-rules", [
        'weekday' => 99,
    ])->assertNotFound();
});

it('returns 404 for a patient on every availability route', function () {
    $rule = AvailabilityRule::factory()->create(['doctor_id' => $this->doctor->id]);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/center/doctors/{$this->doctor->id}/availability-rules")->assertNotFound();
    $this->postJson("/api/v1/center/doctors/{$this->doctor->id}/availability-rules", [
        'weekday' => 1,
        'start_time' => '09:00',
        'end_time' => '12:00',
    ])->assertNotFound();
    $this->patchJson("/api/v1/center/availability-rules/{$rule->id}", [
        'start_time' => '10:00',
    ])->assertNotFound();
});

it('lets an admin manage any doctor rules', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/center/doctors/{$this->otherDoctor->id}/availability-rules", [
        'weekday' => 1,
        'start_time' => '09:00',
        'end_time' => '12:00',
    ])->assertStatus(201);

    $this->getJson("/api/v1/center/doctors/{$this->otherDoctor->id}/availability-rules")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('requires authentication', function () {
    $this->getJson("/api/v1/center/doctors/{$this->doctor->id}/availability-rules")->assertUnauthorized();
    $this->postJson("/api/v1/center/doctors/{$this->doctor->id}/availability-rules", [])->assertUnauthorized();
});
