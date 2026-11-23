<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('api')->group(function () {
        Route::get('/api/v1/_test/protected', fn () => response()->json(['data' => 'ok']))
            ->middleware('auth:sanctum');

        Route::post('/api/v1/_test/validate', fn (Request $request) => $request->validate([
            'email' => ['required', 'email'],
        ]));
    });
});

it('returns the envelope for an unknown route', function () {
    $this->getJson('/api/v1/does-not-exist')
        ->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND')
        ->assertJsonStructure(['message', 'code']);
});

it('returns the envelope for an unauthenticated request', function () {
    $this->getJson('/api/v1/_test/protected')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'UNAUTHENTICATED');
});

it('returns the envelope for a validation failure', function () {
    $this->postJson('/api/v1/_test/validate', ['email' => 'not-an-email'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['message', 'code', 'errors' => ['email']]);
});

it('translates the envelope with Accept-Language', function () {
    $this->withHeader('Accept-Language', 'ar')
        ->postJson('/api/v1/_test/validate', ['email' => 'not-an-email'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'يجب أن يكون البريد الإلكتروني بريدًا إلكترونيًا صالحًا.');
});

it('attaches a request id to every response', function () {
    $response = $this->getJson('/api/v1/ping');

    expect($response->headers->get('X-Request-ID'))->toBeString()->not->toBeEmpty();
});

it('echoes a safe incoming request id', function () {
    $this->withHeader('X-Request-ID', 'client-request-123')
        ->getJson('/api/v1/ping')
        ->assertHeader('X-Request-ID', 'client-request-123');
});

it('replaces an unsafe incoming request id', function () {
    $response = $this->withHeader('X-Request-ID', 'bad id with spaces')
        ->getJson('/api/v1/ping');

    expect($response->headers->get('X-Request-ID'))->not->toBe('bad id with spaces');
});
