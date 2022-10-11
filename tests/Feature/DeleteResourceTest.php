<?php
namespace Tests\Feature;

use Laravel\Sanctum\Sanctum;

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
        Sanctum::actingAs($this->alice);

        $this->deleteJson("/api/users/1/posts/1")->assertStatus(204);
    }

    /** @test */
    function cannot_delete_resource_403()
    {
        Sanctum::actingAs($this->bob);

        $this->deleteJson("/api/users/1/posts/1")->assertStatus(403);
    }
}
