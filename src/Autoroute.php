<?php
namespace Eyf;

use Illuminate\Routing\Router;
use Illuminate\Support\Str;

class Autoroute {

    const IS_VERB = '/^(get|post|put|delete|any)$/i';

    protected $router;
    protected $resourceNames = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];

    protected $ctrlSeparator = '.';
    protected $routeSeparator = '.';
    protected $strFilters = ['snake', 'slug'];
    protected $ignoreIndex = true;
    protected $pluralizeResource = true;
    protected $fixResourceNames = true;
    protected $constraints = [];

    public function __construct(Router $router, array $options = []) {
        $this->router = $router;

        if (isset($options['ctrl_separator'])) {
            $this->ctrlSeparator = $options['ctrl_separator'];
        }
        if (isset($options['route_separator'])) {
            $this->routeSeparator = $options['route_separator'];
        }
        if (isset($options['str_filters'])) {
            $this->strFilters = $options['str_filters'];
        }
        if (isset($options['ignore_index'])) {
            $this->ignoreIndex = $options['ignore_index'];
        }
        if (isset($options['resource_plural'])) {
            $this->pluralizeResource = $options['resource_plural'];
        }
        if (isset($options['resource_names'])) {
            $this->fixResourceNames = $options['resource_names'];
        }
        if (isset($options['constraints'])) {
            $this->constraints = $options['constraints'];
        }
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
        $prefix = trim($this->router->getLastGroupPrefix(), '/');
        $prefix = explode('/', $prefix);
        $prefix = array_filter($prefix, function ($segment) {
            return strpos($segment, '{') === false;
        });

        if ($verb === 'resource') {
            return $this->makeResourceRoute($ctrl, $options, $prefix);
        }

        $controller = explode($this->ctrlSeparator, $ctrl);
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
        $controller = explode($this->ctrlSeparator, $ctrl);
        $controllerStr = $this->getControllerString($controller, true);

        if ($prefix) {
            $resource = array_filter($controller, function ($segment) use ($prefix) {
                return !in_array($segment, $prefix);
            });
        } else {
            $resource = $controller;
        }

        $resource = implode('.', $resource);
        if (!array_key_exists('names', $options) && $this->fixResourceNames) {
            $options['names'] = $this->getResourceNames($resource, $controller);
        }

        if ($this->pluralizeResource) {
            $resource = str_plural($resource);
        }

        return $this->router->resource($resource, $controllerStr, $options);
    }

    public function getResourceNames($resource, $controller)
    {
        $names = [];
        foreach ($this->resourceNames as $name) {
            $names[$name] = implode(
                $this->routeSeparator,
                array_merge($controller, [$name])
            );
        }
        return $names;
    }

    protected function getControllerString(array $controller, $isResource = false)
    {
        if (!$isResource) {
            $action = array_pop($controller);
        }

        $controller = array_map(function($segment) {
            return ucfirst(camel_case($segment));
        }, $controller);

        $controller = implode('\\', $controller).'Controller';

        return isset($action) ? $controller.'@'.$action : $controller;
    }

    protected function getPathname(array $controller, array $prefix)
    {
        if ($this->ignoreIndex) {
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
            return $autoroute->transformName($str);
        }, $controller);

        return implode($this->routeSeparator, $controller);
    }

    protected function transformName($name)
    {
        foreach ($this->strFilters as $filter) {
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
