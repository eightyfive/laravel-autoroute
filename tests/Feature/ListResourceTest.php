<?php
namespace Tests\Feature;

use App\Models\Post;

class ListResourceTest extends FeatureTestCase
{
    /** @test */
    function can_list_resource()
    {
        $this->getJson("/api/users")
            ->assertStatus(200)
            ->assertJsonPath("data.0.name", "Alice")
            ->assertJsonPath("data.1.name", "Bob");
    }

    /** @test */
    function cannot_list_resource_403()
    {
        $this->actingAs($this->bob)
            ->getJson("/api/users/1/posts")
            ->assertStatus(403);
    }

    /** @test */
    function can_list_resource_deep()
    {
        Post::create(["title" => "Post 1", "user_id" => 1]);
        Post::create(["title" => "Post 2", "user_id" => 2]);
        Post::create(["title" => "Post 3", "user_id" => 1]);

        $this->actingAs($this->alice)
            ->getJson("/api/users/1/posts")
            ->assertStatus(200)
            ->assertJsonPath("data.0.id", 1)
            ->assertJsonPath("data.0.user_id", null)
            ->assertJsonPath("data.1.id", 3);
    }
}
