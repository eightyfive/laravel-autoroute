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
        'filters' => ['snake', 'slug'],
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
        $this->filters = array_filter($this->options['filters'], function($filter) {
            return in_array($filter, ['slug', 'snake', 'camel']);
        });
    }

    public function make(array $definitions)
    {
        $routes = [];
        foreach ($definitions as $route) {
            list(
                $ctrl,
                $pathname,
                $verb,
                $name,
                $options) = $this->normalizeRoute($route);

            $routes[] = $this->makeRoute($ctrl, $pathname, $verb, $name, $options);
        }
        return $routes;
    }

    protected function makeRoute($ctrl, $pathname, $verb, $name, $options)
    {
        if ($verb === 'resource') {
            return $this->makeResourceRoute($ctrl, $options);
        }

        $controller = explode($this->options['ctrl_separator'], $ctrl);
        $controllerStr = $this->getControllerString($controller);

        if (!$pathname) {
            $pathname = $this->getPathname($controller);
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
                if (!isset($this->constraints[$key])) {
                    throw new \Exception("Requirement not found for '{$key}'");
                }
                $where[$key] = $this->constraints[$key];
            }
            $route->where($where);
        }
        return $route;
    }

    protected function makeResourceRoute($ctrl, $options)
    {
        $controller = explode($this->options['ctrl_separator'], $ctrl);
        $controllerStr = $this->getControllerString($controller, true);

        $resource = array_pop($controller);
        $namespace = implode('.', $controller);

        if (!array_key_exists('names', $options)) {
            $options['names'] = $this->getResourceNames($resource, $namespace);
        }

        return $this->router->resource($resource, $controllerStr, $options);
    }

    public function getResourceNames($resource, $namespace)
    {
        $names = [];
        foreach ($this->options['resource_names'] as $key => $name) {
            if ($name === true) {
                $name = $key;
            }
            $names[$key] = implode('.', [$namespace, $resource, $name]);
        }
        return $names;
    }

    protected function getControllerString($controller, $isResource = false)
    {
        $controller = array_map(function($segment) {
            return ucfirst($segment);
        }, $controller);

        if (!$isResource) {
            $action = array_pop($controller);
        }
        $controller = implode('\\', $controller).'Controller';

        return isset($action) ? $controller.'@'.$action : $controller;
    }

    protected function getPathname($controller)
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
            return $autoroute->transformName($str);
        }, $pathname);

        return implode('/', $pathname);
    }

    protected function getRouteName($controller)
    {
        $autoroute = $this;
        $controller = array_map(function($str) use ($autoroute) {
            return $autoroute->transformName($str);
        }, $controller);

        return implode($this->options['route_separator'], $controller);
    }

    protected function transformName($name)
    {
        foreach ($this->filters as $filter) {
            $name = call_user_func([Str::class, $filter], $name);
        }
        return $name;
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
        }

        if (!$verb) {
            $verb = 'get';
        }

        return [$ctrl, $pathname, $verb, $name, $options];
    }
}
