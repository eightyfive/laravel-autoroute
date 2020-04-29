<?php
namespace Eyf\Autoroute;

use Illuminate\Routing\Router;

class Autoroute
{
    protected $namer;
    protected $router;

    public function __construct(Router $router, NamerInterface $namer)
    {
        $this->namer = $namer;
        $this->router = $router;
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
            // Create route
            $route = call_user_func([$this->router, $verb], $path, $options);

            // Add parameter constraints
            foreach ($constraints as $param => $constraint) {
                $route->where($param, $constraint);
            }

            // Make default route name
            if (!isset($options['as'])) {
                $group = last($this->router->getGroupStack());

                $name = $this->namer->getRouteName(
                    $options['uses'],
                    $group ? $group['namespace'] : ''
                );

                $route->name($name);
            }
        }
    }
}
