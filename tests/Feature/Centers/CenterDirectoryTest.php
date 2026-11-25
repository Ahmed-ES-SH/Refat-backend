<?php

use App\Enums\Specialty;
use App\Models\Center;
use App\Models\Doctor;

it('lists only active centers', function () {
    Center::factory()->active()->create(['name' => 'Active Center']);
    Center::factory()->create(['name' => 'Pending Center']);
    Center::factory()->suspended()->create(['name' => 'Suspended Center']);

    $this->getJson('/api/v1/centers')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Active Center');
});

it('exposes the specialties of the active doctors', function () {
    $center = Center::factory()->active()->create();

    Doctor::factory()->for($center)->create([
        'specialty' => Specialty::Cardiology,
        'is_active' => true,
    ]);
    Doctor::factory()->for($center)->create([
        'specialty' => Specialty::Dentistry,
        'is_active' => true,
    ]);
    Doctor::factory()->for($center)->create([
        'specialty' => Specialty::Psychiatry,
        'is_active' => false,
    ]);

    $this->getJson('/api/v1/centers')
        ->assertOk()
        ->assertJsonPath('data.0.specialties', ['cardiology', 'dentistry']);
});

it('filters the directory by city', function () {
    Center::factory()->active()->create(['name' => 'Riyadh Center', 'city' => 'Riyadh']);
    Center::factory()->active()->create(['name' => 'Jeddah Center', 'city' => 'Jeddah']);

    $this->getJson('/api/v1/centers?city=Riyadh')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Riyadh Center');
});

it('filters the directory by specialty', function () {
    $cardiology = Center::factory()->active()->create(['name' => 'Heart Center']);
    $dental = Center::factory()->active()->create(['name' => 'Smile Center']);

    Doctor::factory()->for($cardiology)->create([
        'specialty' => Specialty::Cardiology,
        'is_active' => true,
    ]);
    Doctor::factory()->for($dental)->create([
        'specialty' => Specialty::Dentistry,
        'is_active' => true,
    ]);

    $this->getJson('/api/v1/centers?specialty=cardiology')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Heart Center');
});

it('searches the directory by name', function () {
    Center::factory()->active()->create(['name' => 'Al Noor Medical Center']);
    Center::factory()->active()->create(['name' => 'Al Hayat Clinic']);

    $this->getJson('/api/v1/centers?search=Noor')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Al Noor Medical Center');
});

it('shows a public center to a guest', function () {
    $center = Center::factory()->active()->create();

    $this->getJson("/api/v1/centers/{$center->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $center->id);
});

it('hides a suspended center from the public detail', function () {
    $center = Center::factory()->suspended()->create();

    $this->getJson("/api/v1/centers/{$center->id}")
        ->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND');
});

it('uses the shared list envelope', function () {
    Center::factory()->active()->count(2)->create();

    $this->getJson('/api/v1/centers')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['page', 'per_page', 'total']])
        ->assertJsonPath('meta.page', 1)
        ->assertJsonPath('meta.total', 2);
});

it('rejects an unknown specialty', function () {
    $this->getJson('/api/v1/centers?specialty=astrology')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('specialty');
});

it('rejects a per_page above the cap', function () {
    $this->getJson('/api/v1/centers?per_page=500')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');
});

it('does not let a search term widen the query', function () {
    Center::factory()->active()->create(['name' => 'Al Noor Medical Center']);
    Center::factory()->active()->create(['name' => 'Al Hayat Clinic']);

    $this->getJson('/api/v1/centers?search=%25')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
