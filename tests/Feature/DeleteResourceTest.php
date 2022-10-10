<?php
namespace Tests\Feature;

use App\Models\Post;

class DeleteResourceTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Post::create(["title" => "Post 1", "user_id" => 1]);
    }

    /** @test */
    function can_delete_resource()
    {
        $this->actingAs($this->alice)
            ->deleteJson("/api/users/1/posts/1")
            ->assertStatus(204);
    }

    /** @test */
    function cannot_delete_resource_403()
    {
        $this->actingAs($this->bob)
            ->deleteJson("/api/users/1/posts/1")
            ->assertStatus(403);
    }
}
