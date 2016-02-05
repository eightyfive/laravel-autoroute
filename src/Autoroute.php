<?php
namespace Eyf;

use Illuminate\Routing\Router;
use Illuminate\Support\Str;

class Autoroute {

    const IS_VERB = '/^(get|post|put|delete|any)$/i';

    protected $router;
    protected $routes = [];
    protected $requirements;
    protected $options;

    protected $defaults = [
        'ignore_index' => true,
        'ctrl_separator' => '.',
        'route_separator' => '.',
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

    public function __construct(Router $router, array $routes, array $requirements, $options = [])
    {
        $this->router = $router;
        $this->requirements = $requirements;
        $this->options = array_merge($this->defaults, $options);
        $this->filters = array_filter($this->options['filters'], function($filter) {
            return in_array($filter, ['slug', 'snake', 'camel']);
        });

        $this->routes['app'] = [];

        foreach ($routes as $key => $route) {
            if (is_numeric($key)) {
                array_push($this->routes['app'], $route);
            } else {
                $this->routes[$key] = $route; // Here `$route` is an array of routes.
            }
        }
    }

    public function make($namespace = 'app')
    {
        if (!array_key_exists($namespace, $this->routes)) {
            throw new \Exception('Namespace "'.$namespace.'" doesn\'t exist.');
        }
        return $this->makeRoutes($this->routes[$namespace], $namespace);
    }

    public function makeRoutes(array $definitions, $namespace)
    {
        $routes = [];
        foreach ($definitions as $route) {
            list(
                $ctrl,
                $pathname,
                $verb,
                $name,
                $options) = call_user_func([$this, 'normalizeRoute'], $route);

            $routes[] = $this->doMakeRoute($namespace, $ctrl, $pathname, $verb, $name, $options);
        }
        return $routes;
    }


    public function makeRoute($ctrl, $pathname, $verb = null, $name = null, $namespace = 'app')
    {
        list(
            $ctrl,
            $pathname,
            $verb,
            $name,
            $options) = $this->normalizeRoute([$ctrl, $pathname, $verb, $name]);

        return $this->doMakeRoute($namespace, $ctrl, $pathname, $verb, $name, $options);
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
                $verb = array_map(function($verbe) {
                    return strtolower($verbe);
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

    protected function doMakeRoute($namespace, $ctrl, $pathname, $verb, $name, $options)
    {
        if ($verb === 'resource') {
            return $this->doMakeResourceRoute($ctrl, $namespace, $options);
        }

        list($namespace, $ctrl, $action) = $this->parseCtrl($ctrl, $namespace);

        $controller = $this->getController($ctrl, $action, $namespace);

        if (!$pathname) {
            $pathname = $this->getPathname($ctrl, $action, $namespace);
        }
        if (!$name) {
            $name = $this->getRouteName($ctrl, $action, $namespace);
        }

        // dd($ctrl, $pathname, $verb, $name);

        if (is_array($verb)) {
            $route = call_user_func([$this->router, 'match'], $verb, $pathname, $controller);
        } else {
            $route = call_user_func([$this->router, $verb], $pathname, $controller);
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
                if (!isset($this->requirements[$key])) {
                    throw new \Exception("Requirement not found for '{$key}'");
                }
                $where[$key] = $this->requirements[$key];
            }
            $route->where($where);
        }
        return $route;
    }

    protected function doMakeResourceRoute($resource, $namespace, $options)
    {
        $controller = $this->getResourceController($resource, $namespace);

        if (!array_key_exists('names', $options)) {
            $options['names'] = $this->getResourceNames($resource, $namespace);
        }

        return $this->router->resource($resource, $controller, $options);
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

    protected function parseCtrl($ctrl, $namespace)
    {
        $segments = explode($this->options['ctrl_separator'], $ctrl);
        $action = array_pop($segments);
        $ctrl = array_pop($segments);

        if ($namespace === 'app') {
            $namespace = $segments;
        } else {
            $namespace = array_merge([$namespace], $segments);
        }

        return [$namespace, $ctrl, $action];
    }

    protected function getController($ctrl, $action, array $namespace)
    {
        $ctrl = ucfirst($ctrl);

        if (!count($namespace)) {
            return "{$ctrl}Controller@{$action}";
        }

        $namespace = array_map(function($segment) {
            return ucfirst($segment);
        }, $namespace);

        $namespace = implode('\\', $namespace);

        return "{$namespace}\\{$ctrl}Controller@{$action}";
    }

    protected function getResourceController($resource, $namespace)
    {
        $resource = explode('.', $resource);

        $controller = array_map(function($segment) {
            return ucfirst($segment);
        }, $resource);

        if ($namespace !== 'app') {
            array_unshift($controller, ucfirst($namespace));
        }

        return implode('\\', $controller).'Controller';
    }

    protected function getPathname($ctrl, $action, array $namespace)
    {
        $pathname = [$ctrl, $action];

        if (count($namespace)) {
            $pathname = array_merge($namespace, $pathname);
        }

        if ($this->options['ignore_index']) {
            $pathname = array_filter($pathname, function($crumb) {
                return $crumb !== 'index';
            });
        }
        return implode('/', $pathname);
    }

    protected function getRouteName($ctrl, $action, array $namespace)
    {
        $ctrl   = $this->transformName($ctrl);
        $action = $this->transformName($action);
        $sep    = $this->options['route_separator'];

        if (!count($namespace)) {
            return "{$ctrl}{$sep}{$action}";
        }

        $autoroute = $this;
        $pieces = array_map(function($segment) use ($autoroute) {
            return $autoroute->transformName($segment);
        }, $namespace);

        array_push($pieces, $route);

        return implode($sep, $pieces);
    }

    protected function transformName($name)
    {
        foreach ($this->filters as $filter) {
            $name = call_user_func([Str::class, $filter], $name);
        }
        return $name;
    }
}
