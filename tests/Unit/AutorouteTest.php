<?php
namespace Tests\Unit;

use Illuminate\Routing\Router;
use Illuminate\Events\Dispatcher;

use Tests\TestCase;
use Tests\Autoroute;

final class AutorouteTest extends TestCase
{
    protected Autoroute $autoroute;
    protected Router $router;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAutoroute();
        $this->autoroute->createGroup("api.yaml");
    }

    protected function createAutoroute()
    {
        $this->router = new Router(new Dispatcher());
        $this->autoroute = new Autoroute(
            $this->router,
            __DIR__ . "/../../public"
        );
    }

    protected function resetAutoroute()
    {
        unset($this->autoroute);
        $this->createAutoroute();
    }

    /** @test */
    public function get_prefix_from_file_name(): void
    {
        $this->assertEquals(
            $this->autoroute->getPrefixFromFileName("api.yaml"),
            "api"
        );

        $this->assertEquals(
            $this->autoroute->getPrefixFromFileName("dir_name/web_api.yaml"),
            "web_api"
        );

        $this->assertEquals(
            $this->autoroute->getPrefixFromFileName("./dir_name/internal.yaml"),
            "internal"
        );
    }

    /** @test */
    public function creates_group(): void
    {
        $this->assertNotEquals($this->autoroute->getGroup("api"), null);
    }

    /** @test */
    public function creates_group_custom_prefix(): void
    {
        $this->resetAutoroute();

        $this->autoroute->createGroup("api.yaml", [
            "prefix" => "external",
        ]);

        $this->assertEquals($this->autoroute->getGroup("api"), null);
        $this->assertNotEquals($this->autoroute->getGroup("external"), null);
    }

    /** @test */
    public function registers_routes(): void
    {
        $this->assertNotEquals($this->getRoute("POST", "api/users"), null);
        $this->assertNotEquals($this->getRoute("GET", "api/users"), null);
        $this->assertNotEquals(
            $this->getRoute("GET", "api/users/{user_id}"),
            null
        );
        $this->assertNotEquals(
            $this->getRoute("GET", "api/users/{user_id}/posts"),
            null
        );

        $route = $this->getRoute("POST", "api/login");

        $this->assertEquals($route->getActionName(), "SessionController@login");
    }

    /** @test */
    public function creates_validation_rules(): void
    {
        $rules = $this->autoroute->getRequest("api/users", "post");

        $this->assertTrue(isset($rules["name"]));
        $this->assertTrue(isset($rules["email"]));
        $this->assertTrue(isset($rules["password"]));
        $this->assertTrue(isset($rules["device_name"]));

        $this->assertEquals($rules["name"], ["required", "string"]);
        $this->assertEquals($rules["email"], ["required", "string", "email"]);
        $this->assertEquals($rules["password"], [
            "required",
            "string",
            "min:8",
        ]);
        $this->assertEquals($rules["device_name"], ["string", "between:5,10"]);
    }

    /** @test */
    public function is_secured(): void
    {
        $this->assertFalse(
            $this->autoroute->isSecured("api/login", Autoroute::METHOD_CREATE)
        );

        $this->assertTrue(
            $this->autoroute->isSecured(
                "api/users/{user_id}/posts/{post_id}",
                Autoroute::METHOD_READ
            )
        );
    }

    //
    // PROTECTED
    //

    protected function getRoutes()
    {
        return $this->router->getRoutes();
    }

    protected function getRoute(string $method, string $uri)
    {
        $routes = $this->getRoutes()->getRoutesByMethod();

        return $routes[$method][$uri] ?? null;
    }
}
