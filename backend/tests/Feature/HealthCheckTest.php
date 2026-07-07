<?php

/*
|--------------------------------------------------------------------------
| Clinex — Health Check Test
|--------------------------------------------------------------------------
*/

it('returns ok on health check without authentication', function () {
    $response = $this->getJson('/api/health');

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'timestamp',
            'version',
        ])
        ->assertJson([
            'status' => 'ok',
            'version' => '1.0.0',
        ]);
});
