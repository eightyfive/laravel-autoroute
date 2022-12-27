<?php
namespace Tests\Unit;

use Illuminate\Routing\Router;
use Illuminate\Events\Dispatcher;

use Tests\TestCase;
use Tests\Autoroute;

class AutorouteTest extends TestCase
{
    protected Autoroute $autoroute;
    protected Router $router;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetAutoroute();
        $this->autoroute->createGroup("api.yaml", [
            "prefix" => "api",
        ]);
    }

    protected function resetAutoroute()
    {
        $this->router = new Router(new Dispatcher());
        $this->autoroute = new Autoroute(
            $this->router,
            __DIR__ . "/../../public"
        );
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
        $rules = $this->autoroute->getValidationRulesByRoute(
            "api/users",
            "post"
        );

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
    public function schema_to_array(): void
    {
        $schema = $this->autoroute->getSchemaOf("User");

        $this->assertIsArray($schema);

        $this->assertEquals(
            ["id", "name", "email", "posts"],
            array_keys($schema)
        );

        $this->assertEquals($schema["posts"], [
            "id" => "integer",
            "title" => "string",
        ]);
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
