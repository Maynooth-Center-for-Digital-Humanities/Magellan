<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\User as User;
use App\Entry as Entry;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EntryApiInsertViewDeleteTest extends TestCase
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
    /**
     * Not Authenticated Request
     *
     * @return void
     */
    public function testInsertTestFail()
    {
        $response = $this->get('/api/show/1/');
        $response->assertStatus(401);
    }

    public function testInsertEntryCorrectly()
    {
        $user=factory(User::class)->create();
        $token = $user->createToken('ApiIngestion');
        $headers = ['Authorization' => "Bearer $token->accessToken"];
        $entry = factory(Entry::class)->create();

        $response = $this->json('POST', '/api/add',json_decode($entry->element,true),$headers)
                    ->assertStatus(200);

    }


}
