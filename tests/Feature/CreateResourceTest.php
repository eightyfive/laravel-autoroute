<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\TestCase;
use App\Models\User;

class CreateResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $alice;
    protected User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        // Alice
        $this->alice = User::create([
            "name" => "Alice",
            "email" => "alice@example.org",
            "password" => "password",
        ]);

        // Bob
        $this->bob = User::create([
            "name" => "Bob",
            "email" => "bob@example.org",
            "password" => "password",
        ]);
    }

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
    function cannot_create_resource_404()
    {
        $this->postJson("/api/users/10000000/posts", [
            "title" => "Post 1",
        ])->assertStatus(404);
    }

    // TODO: Install auth:sanctum in Test env
    function cannot_create_resource_401()
    {
        $this->postJson("/api/users/1/posts", [
            "title" => "Post 1",
        ])->assertStatus(401);
    }

    /** @test */
    function cannot_create_resource_403()
    {
        $this->actingAs($this->alice)
            ->postJson("/api/users/2/posts", [
                "title" => "Post 1",
            ])
            ->assertStatus(403);
    }

    /** @test */
    function can_create_resource_deep()
    {
        $this->actingAs($this->alice)
            ->postJson("/api/users/1/posts", [
                "title" => "Post 1",
            ])
            ->assertStatus(201);
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
