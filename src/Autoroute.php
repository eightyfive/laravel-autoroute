<?php
namespace Eyf;

use Illuminate\Routing\Router;
use Illuminate\Support\Str;

class Autoroute {

    const IS_VERB = '/^(get|post|put|delete|any)$/i';

    protected $router;
    protected $routes;
    protected $requirements;
    protected $options;

    protected $defaults = [
        'ignore_index' => true,
        'separator' => '.',
        'route_name' => '{ctrl}.{action}',
        'filters' => ['snake', 'slug']
    ];

    public function __construct(Router $router, array $routes, array $requirements, $options = [])
    {
        $this->router = $router;
        $this->routes = $routes;
        $this->requirements = $requirements;
        $this->options = array_merge($this->defaults, $options);
        $this->filters = array_filter($this->options['filters'], function($filter) {
            return in_array($filter, ['slug', 'snake', 'camel']);
        });
    }

    public function make()
    {
        $routes = [];
        foreach ($this->routes as $route) {
            $ctrl = key($route);

            if (is_string($ctrl)) {
                $pathname = current($route);
            } else {
                $ctrl = current($route);
                $pathname = null;
            }
            array_shift($route);
            array_unshift($route, $ctrl, $pathname);

            $routes[] = call_user_func_array([$this, 'makeRoute'], $route);
        }
        return $routes;
    }

    public function makeRoute($ctrl, $pathname, $verb = null, $name = null)
    {
        if ($verb) {
            if (is_array($verb)) {
                $match = $verb;
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

        list($ctrl, $action, $namespace) = $this->parseController($ctrl);

        $controller = $this->getController($ctrl, $action, $namespace);

        if (!$pathname) {
            $pathname = $this->getPath($ctrl, $action);
        }
        if (!$name) {
            $name = $this->getRouteName($ctrl, $action);
        }

        // dd($ctrl, $pathname, $verb, $name);

        if (isset($match)) {
            $route = call_user_func([$this->router, 'match'], $match, $pathname, $controller);
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

    protected function parseController($ctrl)
    {
        $crumbs = explode($this->options['separator'], $ctrl);
        $action = array_pop($crumbs);
        $ctrl = array_pop($crumbs);

        $namespace = null;
        if (count($crumbs)) {
            $namespace = implode('\\', array_map(function($crumb) {
                return ucfirst($crumb);
            }, $crumbs));
        }

        return [$ctrl, $action, $namespace];
    }

    protected function getPath($ctrl, $action)
    {
        $pathname = [$ctrl, $action];
        if ($this->options['ignore_index']) {
            if ($ctrl === 'index') {
                array_shift($pathname);
            }
            if ($action === 'index') {
                array_shift($pathname);
            }
        }
        return implode('/', $pathname);
    }

    protected function getRouteName($ctrl, $action)
    {
        $ctrl   = $this->transformName($ctrl);
        $action = $this->transformName($action);

        $route = $this->options['route_name'];
        $route = str_replace('{ctrl}', $ctrl, $route);
        $route = str_replace('{action}', $action, $route);

        return $route;
    }

    protected function getController($ctrl, $action, $namespace = null)
    {
        $controller = $namespace ? [$namespace, '\\'] : [];
        $controller = array_merge($controller, [
            ucfirst($ctrl),
            'Controller@',
            $action
        ]);

        return implode('', $controller);
    }

    protected function transformName($name)
    {
        foreach ($this->filters as $filter) {
            $name = call_user_func([Str::class, $filter], $name);
        }
        return $name;
    }
}