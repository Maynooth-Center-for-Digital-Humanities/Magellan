<?php

namespace Tests\Feature\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LoginTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testExample()
    {
        $this->assertTrue(true);
    }

    public function testBasicTest()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function testRequiresEmailAndLoginUnsuccessful()
    {
        $this->json('POST', 'api/login')
            ->assertStatus(405);
    }

    public function testRequiresEmailAndLoginSuccessful()
    {
        $this->json('GET', 'api/login', ['email' =>'admin@test.com','password'=>'secret'])
            ->assertStatus(200);
    }
}
