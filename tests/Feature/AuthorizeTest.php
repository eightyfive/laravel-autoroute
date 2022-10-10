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

        $this->autoroute = new Autoroute(
            new Router(new Dispatcher()),
            __DIR__ . "/../../routes"
        );
    }

    /** @test */
    public function authorize_create(): void
    {
        [$ability, $args] = $this->autoroute->authorize(
            Autoroute::ACTION_CREATE,
            "/users",
            []
        );

        $this->assertEquals("create", $ability);

        $this->assertEquals(User::class, $args[0]);
    }

    /** @test */
    public function authorize_read(): void
    {
        // Post 1
        Post::create(["title" => "Post 1", "user_id" => 1]);

        [$ability, $args] = $this->autoroute->authorize(
            Autoroute::ACTION_READ,
            "/users/{user_id}/posts/{post_id}",
            [
                "user_id" => "1",
                "post_id" => "1",
            ]
        );

        $this->assertEquals("readUser", $ability);

        $this->assertTrue($this->alice->is($args[0]));
    }

    /** @test */
    public function authorize_update(): void
    {
        [$ability, $args] = $this->autoroute->authorize(
            Autoroute::ACTION_UPDATE,
            "/users/{user_id}",
            [
                "user_id" => "1",
            ]
        );

        $this->assertEquals("update", $ability);

        $this->assertTrue($this->alice->is($args[0]));
    }

    /** @test */
    public function authorize_list(): void
    {
        [$ability, $args] = $this->autoroute->authorize(
            Autoroute::ACTION_LIST,
            "/users/{user_id}/posts",
            [
                "user_id" => "1",
            ]
        );

        $this->assertEquals("listUser", $ability);

        $this->assertEquals(Post::class, $args[0]);
        $this->assertTrue($this->alice->is($args[1]));
    }

    //
    // PROTECTED
    //

    protected function getRoutes()
    {
        $routes = $this->router->getRoutes();
        // $routes->refreshNameLookups();

        return $routes;
    }

    protected function getRoute(string $method, string $uri)
    {
        $routes = $this->getRoutes()->getRoutesByMethod();

        return $routes[$method][$uri] ?? null;
    }
}
