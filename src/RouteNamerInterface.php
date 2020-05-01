<?php
namespace Eyf\Autoroute;

interface RouteNamerInterface
{
    public function getRouteName(string $controller, string $namespace);
    public function getUses(string $compact);
}
