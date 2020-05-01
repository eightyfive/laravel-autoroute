<?php
namespace Eyf\Autoroute;

use Illuminate\Support\Str;

class RouteNamer implements RouteNamerInterface
{
    public function getRouteName(string $uses, string $group = '')
    {
        list($rest, $action) = explode('@', $uses);

        // Controller
        $namespace = explode('\\', $rest);

        $controller = array_pop($namespace);
        $controller = str_replace('Controller', '', $controller);

        // Controller namespace
        $group = str_replace('App\\Http\\Controllers', '', $group);

        if (!empty($group)) {
            $group = trim($group, '\\');
            $group = explode('\\', $group);

            $namespace = array_merge($group, $namespace);
        }

        $route = array_merge($namespace, [$controller, $action]);
        $route = array_map(function ($name) {
            return Str::snake($name);
        }, $route);

        return implode('.', $route);
    }

    public function getUses(string $compact)
    {
        $namespace = explode('.', $compact);

        // Action
        $action = array_pop($namespace);

        // Controller
        $controller = array_pop($namespace);
        $controller = ucfirst($controller) . 'Controller';

        // Namespace
        $namespace = array_map(function ($name) {
            return ucfirst($name);
        }, $namespace);

        // "uses" Laravel name
        array_push($namespace, $controller);

        $controller = implode('\\', $namespace);

        return "{$controller}@{$action}";
    }
}
