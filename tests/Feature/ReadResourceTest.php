<?php
namespace Tests\Feature;

use App\Models\Post;

class ReadResourceTest extends FeatureTestCase
{
    /** @test */
    function can_read_resource()
    {
        $res = $this->getJson("/api/users/1")
            ->assertStatus(200)
            ->assertJsonPath("data.name", "Alice");
    }

    /** @test */
    function cannot_read_resource_403()
    {
        // Post 1
        Post::create(["title" => "Post 1", "user_id" => 1]);

        $this->actingAs($this->bob)
            ->getJson("/api/users/1/posts/1")
            ->assertStatus(403);
    }

    /** @test */
    function can_read_resource_deep()
    {
        // Post 1
        Post::create(["title" => "Post 1", "user_id" => 1]);

        $this->actingAs($this->alice)
            ->getJson("/api/users/1/posts/1")
            ->assertStatus(200)
            ->assertJsonPath("data.title", "Post 1");
    }
}
