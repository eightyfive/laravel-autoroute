<?php
namespace Eyf;

use Illuminate\Routing\Router;
use Illuminate\Support\Str;

class Autoroute {

    protected $router;
    protected $routes;
    protected $requirements;
    protected $options;

    protected $defaults = [
        'separator' => '.',
        'route_name' => '{ctrl}-{action}',
        'transform_ctrl' => 'snake',
        'transform_action' => 'snake',
    ];

    public function __construct(Router $router, array $routes, array $requirements, $options = [])
    {
        $this->router = $router;
        $this->routes = $routes;
        $this->requirements = $requirements;
        $this->options = array_merge($this->defaults, $options);
    }

    public function make()
    {
        $routes = [];
        foreach ($this->routes as $route) {
            $routes[] = call_user_func_array([$this, 'makeRoute'], $route);
        }
        return $routes;
    }

    public function makeRoute($path, $ctrl, $verb = null, $name = null)
    {
        if ($verb) {
            if (1 === preg_match('/^(get|post|put|delete|match|any)$/i', $verb)) {
                $verb = strtolower($verb);
            } else {
                // Verb is the route name
                $name = $verb;
                $verb = null;
            }
        }

        if (!$verb) {
            $verb = 'get';
        }

        list($ctrl, $action, $namespace) = $this->parseController($ctrl);

        $method = $this->getMethod($ctrl, $action, $namespace);

        if (!$path) {
            $path = $this->getPath($ctrl, $action);
        }
        if (!$name) {
            $name = $this->getRouteName($ctrl, $action);
        }

        $route = call_user_func([$this->router, $verb], $path, $method);
        $route->name($name);

        // Requirements
        $params = [];
        $found = preg_match_all("/\{\w+\}/", $path, $params);
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
        // Namespace ?
        $namespace = null;
        $ctrl = explode('\\', $ctrl);
        if (count($ctrl) > 1) {
            $namespace = $ctrl;
            $ctrl = array_pop($namespace);
            $namespace = implode('\\', $namespace);
        } else {
            $ctrl = implode('', $ctrl);
        }

        $ctrl = explode($this->options['separator'], $ctrl);

        return [$ctrl[0], $ctrl[1], $namespace];
    }

    protected function getPath($ctrl, $action)
    {
        $path = [$ctrl, $action];
        if ($ctrl === 'index') {
            array_shift($path);
        }
        if ($action === 'index') {
            array_shift($path);
        }
        return '/'.implode('/', $path);
    }

    protected function getRouteName($ctrl, $action)
    {
        $ctrl   = $this->transformName($ctrl,   $this->options['transform_ctrl']);
        $action = $this->transformName($action, $this->options['transform_action']);

        $route = $this->options['route_name'];
        $route = str_replace('{ctrl}', $ctrl, $route);
        $route = str_replace('{action}', $action, $route);

        return $route;
    }

    protected function getMethod($ctrl, $action, $namespace = null)
    {
        $method = $namespace ? [$namespace, '\\'] : [];
        $method = array_merge($method, [
            ucfirst($ctrl),
            'Controller@',
            $action
        ]);

        return implode('', $method);
    }

    protected function transformName($name, $strategy)
    {
        if ($strategy === 'slug-ish') {
            // for CamelCase ctrl or method names
            // ex: `apiAuth.userSearch` --> `api_auth-user_search`
            $name = Str::snake($name);
            $name = Str::slug($name);
        } else {
            $name = call_user_func([Str::class, $strategy]);
        }
        return $name;
    }
}