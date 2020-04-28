<?php
namespace Eyf\Autoroute;

interface NamerInterface
{
    public function getRouteName(string $controller, string $namespace);
}
