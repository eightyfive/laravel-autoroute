<?php
namespace Tests;

use Illuminate\Routing\Router;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Schema;

use Eyf\Autoroute\Autoroute as Service;
use Eyf\Autoroute\AutorouteResolver;

class Autoroute extends Service
{
    public function __construct(Router $router, string $dir = null)
    {
        parent::__construct($router, new AutorouteResolver(), $dir);
    }

    public function getGroup(string $prefix)
    {
        return parent::getGroup($prefix);
    }

    public function getPrefixFromFileName(string $fileName)
    {
        return parent::getPrefixFromFileName($fileName);
    }

    public function schemaToArray(OpenApi $spec, Schema $schema, $data = [])
    {
        return parent::schemaToArray($spec, $schema);
    }

    public function getValidationRulesByRoute(string $uri, string $method)
    {
        return parent::getValidationRulesByRoute($uri, $method);
    }
}
