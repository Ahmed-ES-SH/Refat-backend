<?php

use App\Models\Center;
use App\Models\DayOff;
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

it('lets center staff add a day off for their own doctor', function () {
    Sanctum::actingAs($this->staff);
    $date = now()->addDay()->toDateString();

    $this->postJson("/api/v1/center/doctors/{$this->doctor->id}/days-off", [
        'date' => $date,
        'reason' => 'Annual leave',
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.doctor_id', $this->doctor->id)
        ->assertJsonPath('data.date', $date)
        ->assertJsonPath('data.reason', 'Annual leave');

    $this->assertDatabaseHas('days_off', [
        'doctor_id' => $this->doctor->id,
        'date' => $date,
        'reason' => 'Annual leave',
    ]);
});

it('rejects a second day off on the same date for the same doctor', function () {
    $date = now()->addDay()->toDateString();
    DayOff::factory()->create([
        'doctor_id' => $this->doctor->id,
        'date' => $date,
    ]);

    Sanctum::actingAs($this->staff);

    $this->postJson("/api/v1/center/doctors/{$this->doctor->id}/days-off", [
        'date' => $date,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('date');
});

it('allows the same date for a different doctor', function () {
    $otherDoctor = Doctor::factory()->create(['center_id' => $this->center->id]);
    $date = now()->addDay()->toDateString();
    DayOff::factory()->create([
        'doctor_id' => $this->doctor->id,
        'date' => $date,
    ]);

    Sanctum::actingAs($this->staff);

    $this->postJson("/api/v1/center/doctors/{$otherDoctor->id}/days-off", [
        'date' => $date,
    ])->assertStatus(201);
});

it('rejects a past date', function () {
    Sanctum::actingAs($this->staff);

    $this->postJson("/api/v1/center/doctors/{$this->doctor->id}/days-off", [
        'date' => now()->subDay()->toDateString(),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('date');
});

it('allows today as a day off', function () {
    Sanctum::actingAs($this->staff);

    $this->postJson("/api/v1/center/doctors/{$this->doctor->id}/days-off", [
        'date' => now()->toDateString(),
    ])->assertStatus(201);
});

it('rejects a reason longer than 150 characters', function () {
    Sanctum::actingAs($this->staff);

    $this->postJson("/api/v1/center/doctors/{$this->doctor->id}/days-off", [
        'date' => now()->addDay()->toDateString(),
        'reason' => str_repeat('a', 151),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');
});

it('lists the days off of a doctor ordered by date', function () {
    DayOff::factory()->create([
        'doctor_id' => $this->doctor->id,
        'date' => now()->addDays(5)->toDateString(),
    ]);
    DayOff::factory()->create([
        'doctor_id' => $this->doctor->id,
        'date' => now()->addDay()->toDateString(),
    ]);

    Sanctum::actingAs($this->staff);

    $this->getJson("/api/v1/center/doctors/{$this->doctor->id}/days-off")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.date', now()->addDay()->toDateString())
        ->assertJsonPath('data.1.date', now()->addDays(5)->toDateString());
});

it('lets center staff update a day off of their own doctor', function () {
    $dayOff = DayOff::factory()->create([
        'doctor_id' => $this->doctor->id,
        'date' => now()->addDay()->toDateString(),
    ]);

    Sanctum::actingAs($this->staff);

    $this->patchJson("/api/v1/center/days-off/{$dayOff->id}", [
        'date' => now()->addDays(2)->toDateString(),
        'reason' => 'Moved',
    ])
        ->assertOk()
        ->assertJsonPath('data.date', now()->addDays(2)->toDateString())
        ->assertJsonPath('data.reason', 'Moved');

    $this->assertDatabaseHas('days_off', [
        'id' => $dayOff->id,
        'date' => now()->addDays(2)->toDateString(),
        'reason' => 'Moved',
    ]);
});

it('keeps a day off valid when the date does not change', function () {
    $date = now()->addDay()->toDateString();
    $dayOff = DayOff::factory()->create([
        'doctor_id' => $this->doctor->id,
        'date' => $date,
    ]);

    Sanctum::actingAs($this->staff);

    $this->patchJson("/api/v1/center/days-off/{$dayOff->id}", [
        'date' => $date,
        'reason' => 'Still on',
    ])->assertOk();
});

it('rejects moving a day off onto a date another one already uses', function () {
    $date = now()->addDay()->toDateString();
    DayOff::factory()->create([
        'doctor_id' => $this->doctor->id,
        'date' => $date,
    ]);
    $dayOff = DayOff::factory()->create([
        'doctor_id' => $this->doctor->id,
        'date' => now()->addDays(2)->toDateString(),
    ]);

    Sanctum::actingAs($this->staff);

    $this->patchJson("/api/v1/center/days-off/{$dayOff->id}", [
        'date' => $date,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('date');
});

it('does not let an update move the day off to another doctor', function () {
    $dayOff = DayOff::factory()->create([
        'doctor_id' => $this->doctor->id,
        'date' => now()->addDay()->toDateString(),
    ]);

    Sanctum::actingAs($this->staff);

    $this->patchJson("/api/v1/center/days-off/{$dayOff->id}", [
        'doctor_id' => $this->otherDoctor->id,
    ])->assertOk();

    expect($dayOff->refresh()->doctor_id)->toBe($this->doctor->id);
});

it('lets center staff delete a day off of their own doctor', function () {
    $dayOff = DayOff::factory()->create(['doctor_id' => $this->doctor->id]);

    Sanctum::actingAs($this->staff);

    $this->deleteJson("/api/v1/center/days-off/{$dayOff->id}")->assertStatus(204);

    $this->assertDatabaseMissing('days_off', ['id' => $dayOff->id]);
});

it('returns 404 when another center touches a day off or a doctor', function () {
    $foreignDayOff = DayOff::factory()->create(['doctor_id' => $this->otherDoctor->id]);

    Sanctum::actingAs($this->staff);

    $this->getJson("/api/v1/center/doctors/{$this->otherDoctor->id}/days-off")->assertNotFound();

    $this->postJson("/api/v1/center/doctors/{$this->otherDoctor->id}/days-off", [
        'date' => now()->addDay()->toDateString(),
    ])->assertNotFound();

    $this->getJson("/api/v1/center/days-off/{$foreignDayOff->id}")->assertNotFound();

    // Authorization runs before validation, so a 422 here would confirm the
    // day off exists in another center.
    $this->patchJson("/api/v1/center/days-off/{$foreignDayOff->id}", [
        'date' => 'not a date',
    ])->assertNotFound();

    $this->deleteJson("/api/v1/center/days-off/{$foreignDayOff->id}")->assertNotFound();
});

it('returns 404 for a patient on every days-off route', function () {
    $dayOff = DayOff::factory()->create(['doctor_id' => $this->doctor->id]);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/center/doctors/{$this->doctor->id}/days-off")->assertNotFound();
    $this->postJson("/api/v1/center/doctors/{$this->doctor->id}/days-off", [
        'date' => now()->addDay()->toDateString(),
    ])->assertNotFound();
    $this->patchJson("/api/v1/center/days-off/{$dayOff->id}", [
        'reason' => 'Nope',
    ])->assertNotFound();
});

it('lets an admin manage any doctor days off', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/center/doctors/{$this->otherDoctor->id}/days-off", [
        'date' => now()->addDay()->toDateString(),
    ])->assertStatus(201);

    $this->getJson("/api/v1/center/doctors/{$this->otherDoctor->id}/days-off")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('requires authentication', function () {
    $this->getJson("/api/v1/center/doctors/{$this->doctor->id}/days-off")->assertUnauthorized();
    $this->postJson("/api/v1/center/doctors/{$this->doctor->id}/days-off", [])->assertUnauthorized();
});
