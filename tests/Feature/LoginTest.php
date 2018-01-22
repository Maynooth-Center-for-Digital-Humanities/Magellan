<?php

namespace Tests\Feature\Feature;

use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
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

    public function testNotFoundTest()
    {
        $response = $this->get('/cavakdkd');

        $response->assertStatus(404);
    }

    /**
     * TEST REQUIREMENT FOR LOGIN
     *
     *
     */
    public function testRequiresEmailAndLoginUnsuccessful()
    {
        $fcontent = file_get_contents(dirname(__DIR__) . "/Feature/json/testRequiresEmailAndLoginUnsuccessful.json");
        $test_json=json_decode($fcontent,true);
        $this->json('GET', 'api/login')
            ->assertStatus(400)
            ->assertJson($test_json);

    }

    public function testRequiresEmailAndLoginSuccessful()
    {
        //$this->printThis($this->json('GET', 'api/login', ['email' =>'admin@test.com','password'=>'secret'])->getContent());
        $this->json('GET', 'api/login', ['email' =>'admin@test.com','password'=>'secret'])
            ->assertStatus(200);

    }
}
