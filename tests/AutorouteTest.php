<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use Illuminate\Routing\Router;
use Illuminate\Events\Dispatcher;

use Eyf\Autoroute\Autoroute;
use Eyf\Autoroute\RouteNamer;

final class AutorouteTest extends TestCase
{
    protected $router;

    protected function getAutoroute($dir = null)
    {
        $this->router = new Router(new Dispatcher());

        $autoroute = new Autoroute($this->router, new RouteNamer(), $dir);

        return $autoroute;
    }

    public function testCreatesRoutes(): void
    {
        $autoroute = $this->getAutoroute();
        $autoroute->create([
            'users' => [
                'get' => [
                    'uses' => 'UserController@get',
                ],
                'post' => [
                    'uses' => 'UserController@store',
                ],
            ],
            'users/{id}' => [
                'where' => [
                    'id' => '[0-9]+',
                ],
                'get' => [
                    'uses' => 'UserController@find',
                ],
                'put' => [
                    'uses' => 'UserController@update',
                ],
            ],
        ]);

        $this->assertRoutes([
            'user.get',
            'user.store',
            'user.find',
            'user.update',
        ]);

        $this->assertMethods([
            'GET' => 2,
            'HEAD' => 2,
            'POST' => 1,
            'PUT' => 1,
        ]);
    }

    public function testCreatesGroup(): void
    {
        $autoroute = $this->getAutoroute();
        $autoroute->create([
            'group' => [
                'namespace' => 'App\\Http\\Controllers\\Api',
                'paths' => [
                    'users' => [
                        'get' => [
                            'uses' => 'UserController@get',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertEquals($this->getRoute('user.get'), null);
        $this->assertNotEquals($this->getRoute('api.user.get'), null);
    }

    public function testAddsConstraints(): void
    {
        $autoroute = $this->getAutoroute();
        $autoroute->create([
            'users/{id}' => [
                'where' => [
                    'id' => '[0-9]+',
                ],
                'get' => [
                    'uses' => 'UserController@get',
                ],
            ],
        ]);

        $route = $this->getRoute('user.get');

        $this->assertNotEquals($route, null);
        $this->assertEquals($route->wheres['id'], '[0-9]+');
    }

    public function testLoadsFile(): void
    {
        $autoroute = $this->getAutoroute();
        $autoroute->load(__DIR__ . '/web.yaml');

        $this->assertRoutes(['post.store']);
    }

    public function testLoadsFiles(): void
    {
        $autoroute = $this->getAutoroute(__DIR__);
        $autoroute->load('api.yaml', 'web.yaml');

        $this->assertRoutes(['user.get', 'user.store', 'post.store']);
    }

    public function testCompact(): void
    {
        $autoroute = $this->getAutoroute();
        $autoroute->create([
            'users' => [
                'get' => 'user.get',
                'post' => 'api.user.store',
            ],
        ]);

        $this->assertRoutes(['user.get', 'api.user.store']);
    }

    protected function getRoutes()
    {
        $routes = $this->router->getRoutes();
        $routes->refreshNameLookups();

        return $routes;
    }

    protected function getRoute($name)
    {
        $routes = $this->getRoutes();

        return $routes->getByName($name);
    }

    protected function assertRoutes(array $names)
    {
        $routes = $this->getRoutes();

        foreach ($names as $name) {
            $this->assertNotEquals($routes->getByName($name), null);
        }
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
