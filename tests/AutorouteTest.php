<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use Illuminate\Routing\Router;
use Illuminate\Events\Dispatcher;

use Eyf\Autoroute\Autoroute;
use Eyf\Autoroute\Namer;

final class AutorouteTest extends TestCase
{
    public function testCreatesRoutes(): void
    {
        $paths = [
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
        ];

        $router = new Router(new Dispatcher());

        $autoroute = new Autoroute($router, new Namer());
        $autoroute->create($paths);

        $routes = $router->getRoutes();

        // Check names
        $routes->refreshNameLookups();

        $this->assertNotEquals($routes->getByName('user.get'), null);
        $this->assertNotEquals($routes->getByName('user.find'), null);
        $this->assertNotEquals($routes->getByName('user.store'), null);
        $this->assertNotEquals($routes->getByName('user.update'), null);

        // Check methods
        $methods = $routes->getRoutesByMethod();

        $this->assertEquals(count($methods['GET']), 2);
        $this->assertEquals(count($methods['HEAD']), 2);
        $this->assertEquals(count($methods['POST']), 1);
        $this->assertEquals(count($methods['PUT']), 1);
    }

    public function testCreatesGroup(): void
    {
        $group = [
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
        ];

        $router = new Router(new Dispatcher());

        $autoroute = new Autoroute($router, new Namer());
        $autoroute->create($group);

        $routes = $router->getRoutes();

        // Check names
        $routes->refreshNameLookups();

        $this->assertEquals($routes->getByName('user.get'), null);
        $this->assertNotEquals($routes->getByName('api.user.get'), null);
    }

    public function testAddsConstraints(): void
    {
        $paths = [
            'users/{id}' => [
                'where' => [
                    'id' => '[0-9]+',
                ],
                'get' => [
                    'uses' => 'UserController@get',
                ],
            ],
        ];

        $router = new Router(new Dispatcher());

        $autoroute = new Autoroute($router, new Namer());
        $autoroute->create($paths);

        $routes = $router->getRoutes();

        // Check names
        $routes->refreshNameLookups();
        $route = $routes->getByName('user.get');

        $this->assertNotEquals($route, null);
        $this->assertEquals($route->wheres['id'], '[0-9]+');
    }
}
