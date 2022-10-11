<?php
namespace Tests\Feature;

use Laravel\Sanctum\Sanctum;

use App\Models\Post;
use App\Models\User;

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
        $res = $this->putJson("/api/users/1", [
            "name" => "LN",
        ])->assertStatus(200);

        $user = User::find(1);

        $this->assertEquals("LN", $user->name);
        $this->assertEquals('""', $res->getContent());
    }

    /** @test */
    function can_update_resource_deep()
    {
        Sanctum::actingAs($this->alice);

        $this->putJson("/api/users/1/posts/1", [
            "title" => "Post 1 (modified)",
        ])
            ->assertStatus(200)
            ->assertJsonPath("data.user_id", null)
            ->assertJsonPath("data.title", "Post 1 (modified)");
    }

    /** @test */
    function cannot_update_resource_422_required()
    {
        Sanctum::actingAs($this->alice);

        $this->putJson("/api/users/1/posts/1", [
            // "title" => "Post 1", // Missing --> 422
        ])->assertStatus(422);
    }

    /** @test */
    function cannot_update_resource_422_invalid()
    {
        Sanctum::actingAs($this->alice);

        $this->putJson("/api/users/1/posts/1", [
            "title" => "P", // Invalid --> 422
        ])->assertStatus(422);
    }
}
