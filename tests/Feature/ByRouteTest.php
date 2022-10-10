<?php
namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\TestCase;
use Tests\AutorouteResolver;

use App\Models\Post;
use App\Models\User;

final class ByRouteTest extends TestCase
{
    use RefreshDatabase;

    protected AutorouteResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new AutorouteResolver();
    }

    /** @test */
    public function create(): void
    {
        // User 1
        User::factory()->create();

        // User 1 post
        $post = $this->resolver->createByRoute(
            "/users/{user_id}/posts",
            ["user_id" => "1"],
            ["title" => "User 1 post"]
        );

        $this->assertInstanceOf(Post::class, $post);
        $this->assertEquals("User 1 post", $post->title);
        $this->assertEquals(1, $post->user_id);
    }
}
