<?php
namespace Tests\Feature;

use Illuminate\Routing\Router;
use Illuminate\Events\Dispatcher;

use Tests\Autoroute;
use App\Models\User;
use App\Models\Post;

final class AuthorizeTest extends FeatureTestCase
{
    protected Autoroute $autoroute;

    protected function setUp(): void
    {
        parent::setUp();

        $router = new Router(new Dispatcher());

        $this->autoroute = new Autoroute($router, __DIR__ . "/../../public");
        $this->autoroute->createGroup("api.yaml");
    }

    /** @test */
    public function authorize_create(): void
    {
        [$ability, $args] = $this->autoroute->authorize(
            Autoroute::ACTION_CREATE,
            "api/users",
            []
        );

        $this->assertEquals("create", $ability);

        [$modelName] = $args;

        $this->assertEquals(User::class, $modelName);
    }

    /** @test */
    public function authorize_read(): void
    {
        // Post 1
        Post::create(["title" => "Post 1", "user_id" => 1]);

        [$ability, $args] = $this->autoroute->authorize(
            Autoroute::ACTION_READ,
            "api/users/{user_id}/posts/{post_id}",
            [
                "user_id" => "1",
                "post_id" => "1",
            ]
        );

        $this->assertEquals("readUser", $ability);

        [$post, $user] = $args;

        $this->assertTrue($this->alice->is($user));
        $this->assertEquals("Post 1", $post->title);
    }

    /** @test */
    public function authorize_update(): void
    {
        [$ability, $args] = $this->autoroute->authorize(
            Autoroute::ACTION_UPDATE,
            "api/users/{user_id}",
            [
                "user_id" => "1",
            ]
        );

        $this->assertEquals("update", $ability);

        [$user] = $args;

        $this->assertTrue($this->alice->is($user));
    }

    /** @test */
    public function authorize_list(): void
    {
        [$ability, $args] = $this->autoroute->authorize(
            Autoroute::ACTION_LIST,
            "api/users/{user_id}/posts",
            [
                "user_id" => "1",
            ]
        );

        $this->assertEquals("listUser", $ability);

        [$modelName, $user] = $args;

        $this->assertEquals(Post::class, $modelName);
        $this->assertTrue($this->alice->is($user));
    }
}
