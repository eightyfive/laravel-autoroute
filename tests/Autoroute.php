<?php
namespace Tests;

use Illuminate\Routing\Router;

use Eyf\Autoroute\Autoroute as Service;
use Eyf\Autoroute\AutorouteResolver;

class Autoroute extends Service
{
    public function __construct(Router $router, string $dir = null)
    {
        parent::__construct($router, new AutorouteResolver(), $dir);
    }

    public function getPrefixFromFileName(string $fileName)
    {
        return parent::getPrefixFromFileName($fileName);
    }
}
