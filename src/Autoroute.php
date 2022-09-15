<?php
namespace Eyf\Autoroute;

use Illuminate\Routing\Router;
use Symfony\Component\Yaml\Yaml;

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

    public function load(array $files, array $parameters = [])
    {
        if ($this->dir) {
            $files = array_map(function ($filename) {
                return "{$this->dir}/{$filename}";
            }, $files);
        }

        foreach ($files as $file) {
            $contents = file_get_contents($file);

            foreach ($parameters as $key => $val) {
                $contents = \str_replace("%" . $key . "%", $val, $contents);
            }

            $routes = Yaml::parse($contents);

            $this->createGroup($routes);
        }
    }

    public function create(array $routes)
    {
        foreach ($routes as $path => $route) {
            if ($path === "group") {
                $this->createGroup($route);
            } else {
                if (isset($route["where"])) {
                    $constraints = $route["where"];

                    unset($route["where"]);
                } else {
                    $constraints = [];
                }

                $this->createRoute($path, $route, $constraints);
            }
        }
    }

    protected function createGroup(array $group)
    {
        $routes = $group["paths"];

        unset($group["paths"]);

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
                $options = compact("uses");
            } else {
                $uses = $options["uses"];
            }

            // Create route
            $route = call_user_func([$this->router, $verb], $path, $options);

            // Add parameter constraints
            foreach ($constraints as $param => $constraint) {
                $route->where($param, $constraint);
            }

            // Default route name
            if (!isset($options["as"])) {
                $group = last($this->router->getGroupStack());

                $name = $this->namer->getRouteName(
                    $uses,
                    $group ? $group["namespace"] : ""
                );

                $route->name($name);
            }
        }
    }
}
