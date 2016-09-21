<?php
namespace Eyf;

use Illuminate\Routing\Router;
use Illuminate\Support\Str;

class Autoroute {

    const IS_VERB = '/^(get|post|put|delete|any)$/i';

    protected $router;
    protected $constraints;
    protected $options;

    protected $defaults = [
        'ctrl_separator' => '.',
        'route_separator' => '.',
        'ignore_index' => true,
        'prefix_resource' => true,
        'transform' => [
            'pathname' => [],
            'route_name' => [],
            'controller' => ['camel', 'ucfirst'],
            'action' => ['camel'],
        ],
        'resource_names' => [
            'index'   => true,
            'create'  => true,
            'store'   => true,
            'show'    => true,
            'edit'    => true,
            'update'  => true,
            'destroy' => true
        ]
    ];

    public function __construct(Router $router, array $constraints, $options = [])
    {
        $this->router = $router;
        $this->constraints = $constraints;
        $this->options = array_merge($this->defaults, $options);
        $this->transform = $this->options['transform'];
    }

    public function make(array $definitions)
    {
        $prefix = trim($this->router->getLastGroupPrefix(), '/');
        $prefix = explode('/', $prefix);
        $prefix = array_filter($prefix, function ($segment) {
            return strpos($segment, '{') === false;
        });

        $routes = [];
        foreach ($definitions as $route) {
            list(
                $ctrl,
                $pathname,
                $verb,
                $name,
                $options) = $this->normalizeRoute($route);

            $routes[] = $this->makeRoute($ctrl, $pathname, $verb, $name, $options, $prefix);
        }
        return $routes;
    }

    protected function makeRoute($ctrl, $pathname, $verb, $name, $options, $prefix)
    {
        if ($verb === 'resource') {
            return $this->makeResourceRoute($ctrl, $options, $prefix);
        }

        $controller = explode($this->options['ctrl_separator'], $ctrl);
        $controllerStr = $this->getControllerString($controller);

        if (!$pathname) {
            $pathname = $this->getPathname($controller, $prefix);
        }
        if (!$name) {
            $name = $this->getRouteName($controller);
        }

        if (is_array($verb)) {
            $route = call_user_func([$this->router, 'match'], $verb, $pathname, $controllerStr);
        } else {
            $route = call_user_func([$this->router, $verb], $pathname, $controllerStr);
        }
        $route->name($name);

        // Requirements
        $params = [];
        $found = preg_match_all("/\{\w+\}/", $pathname, $params);
        if ($found && $found > 0) {
            $params = array_map(function($key) {
                return trim($key, '{}');
            }, $params[0]);

            $where = [];
            foreach ($params as $key) {
                if (isset($this->constraints[$key])) {
                    $where[$key] = $this->constraints[$key];
                }
            }
            $route->where($where);
        }
        return $route;
    }

    protected function makeResourceRoute($ctrl, $options, array $prefix)
    {
        $controller = explode($this->options['ctrl_separator'], $ctrl);
        $controllerStr = $this->getControllerString($controller, true);

        if ($prefix) {
            $resource = array_filter($controller, function ($segment) use ($prefix) {
                return !in_array($segment, $prefix);
            });
        } else {
            $resource = $controller;
        }

        $resource = implode('.', $resource);
        if (!array_key_exists('names', $options) && $this->options['prefix_resource'] === true) {
            $options['names'] = $this->getResourceNames($resource, $controller);
        }

        return $this->router->resource($resource, $controllerStr, $options);
    }

    public function getResourceNames($resource, $controller)
    {
        $names = [];
        foreach ($this->options['resource_names'] as $key => $name) {
            if ($name === true) {
                $name = $key;
            }
            $names[$key] = implode($this->options['route_separator'], array_merge($controller, [$name]));
        }
        return $names;
    }

    protected function getControllerString(array $controller, $isResource = false)
    {
        if (!$isResource) {
            $action = $this->applyFilters(array_pop($controller), $this->transform['action']);
        }

        $controller = array_map(function($segment) {
            return $this->applyFilters($segment, $this->transform['controller']);
        }, $controller);

        $controller = implode('\\', $controller).'Controller';

        return isset($action) ? $controller.'@'.$action : $controller;
    }

    protected function getPathname(array $controller, array $prefix)
    {
        if ($this->options['ignore_index']) {
            $pathname = array_filter($controller, function($str) {
                return $str !== 'index';
            });
        } else {
            $pathname = $controller;
        }

        $autoroute = $this;
        $pathname = array_map(function($str) use ($autoroute) {
            return $autoroute->applyFilters($str, $this->transform['pathname']);
        }, $pathname);

        if ($prefix) {
            $pathname = array_filter($pathname, function ($segment) use ($prefix) {
                return !in_array($segment, $prefix);
            });
        }

        return implode('/', $pathname);
    }

    protected function getRouteName(array $controller)
    {
        $autoroute = $this;
        $controller = array_map(function($str) use ($autoroute) {
            return $autoroute->applyFilters($str, $this->transform['route_name']);
        }, $controller);

        return implode($this->options['route_separator'], $controller);
    }

    protected function applyFilters($str, $filters)
    {
        foreach ($filters as $filter) {
            $str = call_user_func([Str::class, $filter], $str);
        }
        return $str;
    }

    protected function normalizeRoute(array $route)
    {
        $options = [];

        $ctrl = key($route);

        if (is_string($ctrl)) {
            $pathname = current($route);
        } else {
            $ctrl = current($route);
            $pathname = null;
        }

        // Reset index
        $route = array_values($route);

        $name = null;
        $verb = null;
        if (count($route) > 1) {
            $verb = $route[1];
            if (is_array($verb)) {
                // Match
                $verb = array_map(function($httpVerb) {
                    return strtolower($httpVerb);
                }, $verb);
            } else if ($verb === 'resource') {
                if (count($route) > 2) {
                    $options = $route[2];
                }
            } else if (preg_match(Autoroute::IS_VERB, $verb) === 1) {
                // Normalize $verb
                $verb = strtolower($verb);
            } else {
                // $verb is the custom Route $name..
                $name = $verb;
                $verb = null;
            }

            if (isset($verb) && $verb !== 'resource' && !$name && isset($route[2])) {
                $name = $route[2];
            }
        }

        if (!$verb) {
            $verb = 'get';
        }

        return [$ctrl, $pathname, $verb, $name, $options];
    }
}
