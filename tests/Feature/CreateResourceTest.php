<?php
namespace Tests\Feature;

use App\Models\User;

class CreateResourceTest extends FeatureTestCase
{
    /** @test */
    function can_create_resource()
    {
        $this->assertCount(2, User::all());

        $this->postJson("/api/users", [
            "name" => "Dave",
            "email" => "dave@example.org",
            "password" => "password",
        ])->assertStatus(201);

        $this->assertCount(3, User::all());

        $user = User::find(3);

        $this->assertEquals("Dave", $user->name);
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
        $this->actingAs($this->bob)
            ->postJson("/api/users/1/posts", [
                "title" => "Post 1",
            ])
            ->assertStatus(403);
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
