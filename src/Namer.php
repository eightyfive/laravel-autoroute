<?php
namespace Eyf\Autoroute;

use Illuminate\Support\Str;

class Namer implements NamerInterface
{
    public function getRouteName(string $controller, string $namespace)
    {
        $controller = explode('@', $controller);
        $action = array_pop($controller);
        $action = Str::snake($action);

        $controller = array_pop($controller);
        $controller = str_replace('Controller', '', $controller);
        $controller = explode('\\', $controller);
        array_push($controller, $action);

        $namespace = str_replace('App\\Http\\Controllers', '', $namespace);
        if (!empty($namespace)) {
            $namespace = trim($namespace, '\\');
            $namespace = explode('\\', $namespace);
        } else {
            $namespace = [];
        }

        $name = array_merge($namespace, $controller);
        $name = array_map('strtolower', $name);
        $name = implode('.', $name);

        return $name;
    }
}
