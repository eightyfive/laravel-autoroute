<?php
namespace Tests\Feature;

use App\Models\Post;

class ReadResourceTest extends FeatureTestCase
{
    /** @test */
    function can_read_resource()
    {
        $this->getJson("/api/users/1")
            ->assertStatus(200)
            ->assertJson([
                "name" => "Alice",
            ]);
    }

    /** @test */
    function cannot_read_resource_404()
    {
        $this->getJson("/api/users/10000000/posts")->assertStatus(404);
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
            ->assertJson([
                "title" => "Post 1",
            ]);
    }
}
