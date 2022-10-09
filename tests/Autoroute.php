<?php
namespace Tests;

use Illuminate\Routing\Router;

use Eyf\Autoroute\Autoroute as Service;
use Eyf\Autoroute\RouteNamer;

class Autoroute extends Service
{
    public function __construct(Router $router, string $dir = null)
    {
        parent::__construct($router, new RouteNamer(), $dir);
    }

    public function getOperationId(string $uri, string $method)
    {
        return parent::getOperationId($uri, $method);
    }

    public function getModelBaseNames(string $uri)
    {
        return parent::getModelBaseNames($uri);
    }

    public function getModelBaseName(string $segment)
    {
        return parent::getModelBaseName($segment);
    }

    public function getAbilityName(string $uri, string $action)
    {
        return parent::getAbilityName($uri, $action);
    }

    public function getModelNames(string $uri)
    {
        return parent::getModelNames($uri);
    }

    public function getModelsNamespace()
    {
        return parent::getModelsNamespace();
    }
}
