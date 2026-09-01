<?php

test('the health endpoint responds ok', function () {
    $response = $this->getJson(route('api.v1.health'));

    $response
        ->assertOk()
        ->assertJson(['status' => 'ok']);
});
