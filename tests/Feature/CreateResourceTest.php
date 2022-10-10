<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\TestCase;
use App\Models\User;

class CreateResourceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    function can_create_resource()
    {
        $this->assertCount(0, User::all());

        // $response = $this->actingAs($user)->post("/posts", [
        $response = $this->postJson("/api/users", [
            "name" => "Alice",
            "email" => "alice@example.org",
            "password" => "password",
        ]);

        $response->assertStatus(201);

        $this->assertCount(1, User::all());

        $user = User::find(1);

        $this->assertEquals("Alice", $user->name);
        $this->assertEquals("alice@example.org", $user->email);
    }

    /** @test */
    function cannot_create_resource_422_required()
    {
        $this->postJson("/api/users", [
            "name" => "Alice",
            // "email" => "alice@example.org", // Missing email --> 422
            "password" => "password",
        ])->assertStatus(422);
    }

    /** @test */
    function cannot_create_resource_422_invalid()
    {
        $this->postJson("/api/users", [
            "name" => "Alice",
            "email" => "alice_example_org", // Malformed email --> 422
            "password" => "password",
        ])->assertStatus(422);
    }
}
