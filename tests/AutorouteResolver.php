<?php
namespace Tests;

use Eyf\Autoroute\AutorouteResolver as Resolver;

class AutorouteResolver extends Resolver
{
    public function getOperationId(string $uri, string $method): string
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

    public function getAbilityName(string $uri, string $action): string
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

    public function getParameterNames(string $uri)
    {
        return parent::getParameterNames($uri);
    }

    public function getParentParameterName(string $uri): string|null
    {
        return parent::getParentParameterName($uri);
    }

    public function findModelByParameter(string $modelName, string $id)
    {
        return parent::findModelByParameter($modelName, $id);
    }
}
