<?php
namespace Eyf\Autoroute;

use Illuminate\Routing\Router;
use Noodlehaus\Config;

class Autoroute
{
    protected $namer;
    protected $router;

    public function __construct(
        Router $router,
        RouteNamerInterface $namer,
        string $dir = null
    ) {
        $this->namer = $namer;
        $this->router = $router;
        $this->dir = $dir;
    }

    public function load()
    {
        $filepaths = func_get_args();

        if ($this->dir) {
            $filepaths = array_map(function ($filename) {
                return "{$this->dir}/{$filename}";
            }, $filepaths);
        }

        foreach ($filepaths as $filepath) {
            $routes = Config::load($filepath);

            $this->create($routes->all());
        }
    }

    public function create(array $routes)
    {
        foreach ($routes as $path => $route) {
            if ($path === "group") {
                $this->createGroup($route);
            } else {
                if (isset($route['where'])) {
                    $constraints = $route['where'];

                    unset($route['where']);
                } else {
                    $constraints = [];
                }

                $this->createRoute($path, $route, $constraints);
            }
        }
    }

    protected function createGroup(array $group)
    {
        $routes = $group['paths'];

        unset($group['paths']);

        $this->router->group($group, function () use ($routes) {
            $this->create($routes);
        });
    }

    protected function createRoute(
        string $path,
        array $verbs,
        array $constraints = []
    ) {
        foreach ($verbs as $verb => $options) {
            if (is_string($options)) {
                $uses = $this->namer->getUses($options);
                $options = compact('uses');
            } else {
                $uses = $options['uses'] ?? null;
            }
            
            if (!$uses) {
                continue;
            }

            // Create route
            $route = call_user_func([$this->router, $verb], $path, $options);

            // Add parameter constraints
            foreach ($constraints as $param => $constraint) {
                $route->where($param, $constraint);
            }

            // Default route name
            if (!isset($options['as'])) {
                $group = last($this->router->getGroupStack());

                $name = $this->namer->getRouteName(
                    $uses,
                    $group ? $group['namespace'] : ''
                );

                $route->name($name);
            }
        }
    }
}
