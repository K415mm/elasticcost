<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');
        $response->assertStatus(302);

        $response2 = $this->get('/clients');
        $response2->assertStatus(200);

        $response3 = $this->get('/settings/asset-types');
        $response3->assertStatus(200);

        $response4 = $this->get('/settings/scenarios');
        $response4->assertStatus(200);
    }
}
