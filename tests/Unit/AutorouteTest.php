<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

use Illuminate\Routing\Router;
use Illuminate\Events\Dispatcher;

use Tests\Autoroute;

final class AutorouteTest extends TestCase
{
    protected Autoroute $autoroute;
    protected Router $router;

    protected function setUp(): void
    {
        $this->router = new Router(new Dispatcher());
        $this->autoroute = new Autoroute(
            $this->router,
            __DIR__ . "/../../routes"
        );
    }

    /** @test */
    public function get_prefix_from_file_name(): void
    {
        $this->assertEquals(
            $this->autoroute->getPrefixFromFileName("api.yaml"),
            "api"
        );

        $this->assertEquals(
            $this->autoroute->getPrefixFromFileName("routes/web_api.yaml"),
            "web_api"
        );

        $this->assertEquals(
            $this->autoroute->getPrefixFromFileName("./routes/internal.yaml"),
            "internal"
        );
    }

    /** @test */
    public function creates_group(): void
    {
        $this->autoroute->createGroup("api.yaml");

        $this->assertNotEquals($this->autoroute->getGroup("api"), null);
    }

    /** @test */
    public function creates_group_custom_prefix(): void
    {
        $this->autoroute->createGroup("api.yaml", [
            "prefix" => "external",
        ]);

        $this->assertEquals($this->autoroute->getGroup("api"), null);
        $this->assertNotEquals($this->autoroute->getGroup("external"), null);
    }

    /** @test */
    public function registers_routes(): void
    {
        $this->autoroute->createGroup("api.yaml");

        $this->assertNotEquals($this->getRoute("POST", "api/users"), null);
        $this->assertNotEquals($this->getRoute("GET", "api/users"), null);
        $this->assertNotEquals(
            $this->getRoute("GET", "api/users/{user}"),
            null
        );
        $this->assertNotEquals(
            $this->getRoute("GET", "api/users/{user}/posts"),
            null
        );

        $route = $this->getRoute("POST", "api/login");

        $this->assertEquals($route->getActionName(), "SessionController@login");
    }

    /** @test */
    public function creates_validation_rules(): void
    {
        $this->autoroute->createGroup("api.yaml");

        $rules = $this->autoroute->getValidationRules("api", "post", "/users");

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

    // TODO: TOTEST
    public function get_authorize_args(): void
    {
        $this->assertEquals(
            $this->autoroute->authorize(
                Autoroute::ACTION_READ,
                "/users/{user}",
                ["123"]
            ),
            ["read", "User"]
        );

        $this->assertEquals(
            $this->autoroute->authorize(
                Autoroute::ACTION_LIST,
                "/users/{user}/comments",
                ["123"]
            ),
            ["listUser", "Comment", "user"]
        );

        $this->assertEquals(
            $this->autoroute->authorize(
                Autoroute::ACTION_LIST,
                "/users/{user}/profiles/{profile}/comments",
                ["123", "456"]
            ),
            ["listProfile", "Comment", "profile", "user"]
        );
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
