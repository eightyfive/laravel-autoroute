<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

use Illuminate\Routing\Router;
use Illuminate\Events\Dispatcher;

use Tests\Autoroute;

final class AutorouteTest extends TestCase
{
    protected $autoroute;
    protected $router;

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
    public function creates_routes(): void
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
    public function get_ability_name(): void
    {
        $this->assertEquals(
            $this->autoroute->getAbilityName("/users/{user}", "read"),
            "read"
        );

        $this->assertEquals(
            $this->autoroute->getAbilityName("/users/{user}/comments", "list"),
            "listUser"
        );

        $this->assertEquals(
            $this->autoroute->getAbilityName(
                "/users/{user}/profiles/{profile}/comments",
                "list"
            ),
            "listProfile"
        );
    }

    public function get_authorize_args(): void
    {
        $this->assertEquals(
            $this->autoroute->getAuthorizeArgs("/users/{id}", ["123"], "read"),
            ["read", "User"]
        );

        $this->assertEquals(
            $this->autoroute->getAuthorizeArgs(
                "/users/{id}/comments",
                ["123"],
                "list"
            ),
            ["listUser", "Comment", "user"]
        );

        $this->assertEquals(
            $this->autoroute->getAuthorizeArgs(
                "/users/{user}/profiles/{profile}/comments",
                ["123", "456"],
                "list"
            ),
            ["listProfile", "Comment", "profile", "user"]
        );
    }

    /** @test */
    public function get_model_base_names(): void
    {
        $this->assertEquals(
            $this->autoroute->getModelBaseNames("/users/{id}/comments"),
            ["User", "Comment"]
        );

        $this->assertEquals(
            $this->autoroute->getModelBaseNames(
                "/user/{user}/comment/{comment}"
            ),
            ["User", "Comment"]
        );
    }

    /** @test */
    public function get_model_base_name(): void
    {
        $this->assertEquals($this->autoroute->getModelBaseName("user"), "User");
        $this->assertEquals(
            $this->autoroute->getModelBaseName("comments"),
            "Comment"
        );
    }

    /** @test */
    public function get_model_names(): void
    {
        $this->assertEquals(
            $this->autoroute->getModelNames("users/{id}/comments"),
            ["App\\Models\\User", "App\\Models\\Comment"]
        );
    }

    /** @test */
    public function get_models_namespace(): void
    {
        $this->assertEquals(
            $this->autoroute->getModelsNamespace(),
            "App\\Models"
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

    protected function assertMethods(array $verbs)
    {
        $routes = $this->router->getRoutes();
        $methods = $routes->getRoutesByMethod();

        foreach ($verbs as $verb => $count) {
            $this->assertEquals(count($methods[$verb]), $count);
        }
    }
}
