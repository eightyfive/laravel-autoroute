<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\TestCase;
use App\Models\User;

class CreateUserTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    function can_create_users()
    {
        $this->assertCount(0, User::all());

        // $response = $this->actingAs($user)->post("/posts", [
        $response = $this->post("/api/users", [
            "name" => "0x55",
            "email" => "0xfiftyfive@gmail.com",
            "password" => "password",
        ]);

        $response->assertStatus(200);

        $this->assertCount(1, User::all());

        $user = User::find(1);

        $this->assertEquals("0x55", $user->name);
        $this->assertEquals("0xfiftyfive@gmail.com", $user->email);
    }

    /** @test */
    function cannot_create_users()
    {
        $response = $this->post("/api/users", [
            "name" => "0x55",
            // "email" => "0xfiftyfive@gmail.com", // Missing email --> 422
            "password" => "password",
        ]);

        $response->assertStatus(422);
    }
}
