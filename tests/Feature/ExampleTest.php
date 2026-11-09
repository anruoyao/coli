<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_guest_homepage_redirects_to_login_when_no_public_homepage_is_available(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('user.auth.index'));
    }
}
