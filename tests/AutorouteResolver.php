<?php
namespace Tests;

use Eyf\Autoroute\AutorouteResolver as Resolver;

class AutorouteResolver extends Resolver
{
    public function getDefaultControllerString(
        string $uri,
        string $method
    ): string {
        return parent::getDefaultControllerString($uri, $method);
    }

    public function getModelsNamespace()
    {
        return parent::getModelsNamespace();
    }
}
