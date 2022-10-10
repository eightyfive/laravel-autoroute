<?php
namespace Tests\Feature;

use App\Models\Post;

class UpdateResourceTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Post::create(["title" => "Post 1", "user_id" => 1]);
    }

    /** @test */
    function can_update_resource()
    {
        $this->putJson("/api/users/1", [
            "name" => "LN",
        ])
            ->assertStatus(200)
            ->assertJson([
                "name" => "LN",
            ]);
    }

    /** @test */
    function can_update_resource_deep()
    {
        $this->actingAs($this->alice)
            ->putJson("/api/users/1/posts/1", [
                "title" => "Post 1 (modified)",
            ])
            ->assertStatus(200)
            ->assertJson([
                "title" => "Post 1 (modified)",
            ]);
    }

    /** @test */
    function cannot_update_resource_422_required()
    {
        $this->actingAs($this->alice)
            ->putJson("/api/users/1/posts/1", [
                // "title" => "Post 1", // Missing --> 422
            ])
            ->assertStatus(422);
    }

    /** @test */
    function cannot_update_resource_422_invalid()
    {
        $this->actingAs($this->alice)
            ->putJson("/api/users/1/posts/1", [
                "title" => "P", // Invalid --> 422
            ])
            ->assertStatus(422);
    }
}
