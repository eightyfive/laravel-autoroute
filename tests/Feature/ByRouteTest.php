<?php
namespace Tests\Feature;

use Tests\AutorouteResolver;

use App\Models\Post;
use App\Models\User;

final class ByRouteTest extends FeatureTestCase
{
    protected AutorouteResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new AutorouteResolver();
    }

    /** @test */
    public function create_by_route(): void
    {
        // User 1 post
        $post = $this->resolver->createByRoute(
            "/users/{user_id}/posts",
            [
                "user_id" => "1",
            ],
            ["title" => "User 1 post"]
        );

        $this->assertInstanceOf(Post::class, $post);
        $this->assertEquals("User 1 post", $post->title);
        $this->assertEquals(1, $post->user_id);
    }

    /** @test */
    public function read_by_route(): void
    {
        $user = User::find(1);

        $user = $this->resolver->readByRoute("/users/{user_id}", [
            "user_id" => $user,
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals(1, $user->id);
    }

    /** @test */
    public function update_by_route(): void
    {
        Post::create(["title" => "Post 1", "user_id" => 1]);

        $user = User::find(1);
        $post = Post::find(1);

        $post = $this->resolver->updateByRoute(
            "/users/{user_id}/posts/{post_id}",
            [
                "user_id" => $user,
                "post_id" => $post,
            ],
            ["title" => "Post 1 (modified)"]
        );

        $this->assertEquals("Post 1 (modified)", $post->title);
    }

    /** @test */
    public function delete_by_route(): void
    {
        Post::create(["title" => "Post 1", "user_id" => 1]);

        $user = User::find(1);

        $this->resolver->deleteByRoute("/users/{user_id}", [
            "user_id" => $user,
        ]);

        $user = User::find(1);

        $this->assertEquals(null, $user);
    }

    /** @test */
    public function list_by_route(): void
    {
        // User 2
        User::factory()->create();

        // Posts
        Post::create(["title" => "Post 1", "user_id" => 1]);
        Post::create(["title" => "Post 1", "user_id" => 1]);
        Post::create(["title" => "Post 3", "user_id" => 2]); // User 2
        Post::create(["title" => "Post 4", "user_id" => 1]);

        $user = User::find(1);

        $posts = $this->resolver->listByRoute("/users/{user_id}/posts", [
            "user_id" => $user,
        ]);

        $this->assertCount(3, $posts);
    }
}
