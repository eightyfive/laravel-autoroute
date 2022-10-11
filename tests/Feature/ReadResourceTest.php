<?php
namespace Tests\Feature;

use Laravel\Sanctum\Sanctum;

use App\Models\Post;

class ReadResourceTest extends FeatureTestCase
{
    /** @test */
    function can_read_resource()
    {
        $this->getJson("/api/users/1")
            ->assertStatus(200)
            ->assertJsonPath("data.name", "Alice");
    }

    /** @test */
    function cannot_read_resource_401()
    {
        // Alice post
        Post::create(["title" => "Post 1", "user_id" => 1]);

        $this->getJson("/api/users/1/posts/1")->assertStatus(401);
    }

    /** @test */
    function cannot_read_resource_403_1()
    {
        // Alice post
        Post::create(["title" => "Post 1", "user_id" => 1]);

        // Acting as bob
        Sanctum::actingAs($this->bob);

        // Alice post exists, but bob is not authorized
        $this->getJson("/api/users/1/posts/1")->assertStatus(403);
    }

    /** @test */
    function cannot_read_resource_403_2()
    {
        // Bob post
        Post::create(["title" => "Post 1", "user_id" => 2]);

        // Acting as alice
        Sanctum::actingAs($this->alice);

        // Bob post does not exists under alice posts
        // TODO: Should throw `404` ?

        $this->getJson("/api/users/1/posts/1")->assertStatus(403);
    }

    /** @test */
    function cannot_read_resource_404()
    {
        Sanctum::actingAs($this->alice);

        // Post 1 does not exist
        $this->getJson("/api/users/1/posts/1")->assertStatus(404);
    }

    /** @test */
    function can_read_resource_deep()
    {
        // Post 1
        Post::create(["title" => "Post 1", "user_id" => 1]);

        Sanctum::actingAs($this->alice);

        $this->getJson("/api/users/1/posts/1")
            ->assertStatus(200)
            ->assertJsonPath("data.title", "Post 1");
    }
}
