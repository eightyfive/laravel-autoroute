<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

use Illuminate\Routing\Router;
use Illuminate\Events\Dispatcher;

use Tests\Autoroute;

final class AutorouteTest extends TestCase
{
    protected $router;

    protected function createAutoroute()
    {
        $this->router = new Router(new Dispatcher());

        $autoroute = new Autoroute($this->router, __DIR__ . "/../../routes");

        return $autoroute;
    }

    /** @test */
    public function creates_group(): void
    {
        $autoroute = $this->createAutoroute();

        $autoroute->createGroup(
            [
                "prefix" => "api",
                "namespace" => "App\\Http\\Controllers\\Api",
            ],
            "api.yaml"
        );

        $this->assertNotEquals($this->getRoute("GET", "api/users"), null);
        $this->assertNotEquals(
            $this->getRoute("GET", "api/users/{user}"),
            null
        );
        $this->assertNotEquals(
            $this->getRoute("GET", "api/users/{user}/posts"),
            null
        );
    }

    /** @test */
    public function get_ability_name(): void
    {
        $autoroute = $this->createAutoroute();

        $this->assertEquals(
            $autoroute->getAbilityName("/users/{user}", "read"),
            "read"
        );

        $this->assertEquals(
            $autoroute->getAbilityName("/users/{user}/comments", "list"),
            "listUser"
        );

        $this->assertEquals(
            $autoroute->getAbilityName(
                "/users/{user}/profiles/{profile}/comments",
                "list"
            ),
            "listProfile"
        );
    }

    public function get_authorize_args(): void
    {
        $autoroute = $this->createAutoroute();

        $this->assertEquals(
            $autoroute->getAuthorizeArgs("/users/{id}", ["123"], "read"),
            ["read", "User"]
        );

        $this->assertEquals(
            $autoroute->getAuthorizeArgs(
                "/users/{id}/comments",
                ["123"],
                "list"
            ),
            ["listUser", "Comment", "user"]
        );

        $this->assertEquals(
            $autoroute->getAuthorizeArgs(
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
        $autoroute = $this->createAutoroute();

        $this->assertEquals(
            $autoroute->getModelBaseNames("/users/{id}/comments"),
            ["User", "Comment"]
        );

        $this->assertEquals(
            $autoroute->getModelBaseNames("/user/{user}/comment/{comment}"),
            ["User", "Comment"]
        );
    }

    /** @test */
    public function get_model_base_name(): void
    {
        $autoroute = $this->createAutoroute();

        $this->assertEquals($autoroute->getModelBaseName("user"), "User");
        $this->assertEquals(
            $autoroute->getModelBaseName("comments"),
            "Comment"
        );
    }

    /** @test */
    public function get_model_names(): void
    {
        $autoroute = $this->createAutoroute();

        $this->assertEquals($autoroute->getModelNames("users/{id}/comments"), [
            "App\\Models\\User",
            "App\\Models\\Comment",
        ]);
    }

    /** @test */
    public function get_models_namespace(): void
    {
        $autoroute = $this->createAutoroute();

        $this->assertEquals($autoroute->getModelsNamespace(), "App\\Models");
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
