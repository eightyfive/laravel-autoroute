<?php
namespace Tests;

use Eyf\Autoroute\AutorouteResolver as Resolver;

class AutorouteResolver extends Resolver
{
    public function getDefaultOperationId(string $uri, string $method): string
    {
        return parent::getDefaultOperationId($uri, $method);
    }

    public function getModelsNamespace()
    {
        return parent::getModelsNamespace();
    }
}
