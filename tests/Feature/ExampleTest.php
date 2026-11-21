<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_redirects_unauthenticated_visitor_to_login(): void
    {
        $response =$this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_health_endpoint_returns_successful_response(): void
    {
        $response =$this->get('/up');

        $response->assertStatus(200);
    }
}