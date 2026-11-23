<?php

it('responds to the ping endpoint', function () {
    $this->getJson('/api/v1/ping')
        ->assertOk()
        ->assertExactJson(['data' => ['pong' => true]]);
});
