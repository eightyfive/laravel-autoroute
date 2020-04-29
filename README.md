# laravel-autoroute
Autoroute helps you register Laravel routes as an `array` (think YAML).

> "La route? Là où on va, on a pas besoin... De route."

## Install

```
composer require eyf/laravel-autoroute
```

## Usage

```php
// app/Providers/RouteServiceProvider.php

use Symfony\Component\Yaml\Yaml;
use Eyf\Autoroute\Autoroute;

class RouteServiceProvider extends ServiceProvider
{
    public function map(Autoroute $autoroute)
    {
        $routes = Yaml::parseFile(base_path('routes/api.yaml'));

        $autoroute->create($routes);
    }
}
```

### Sample `api.yaml`

```yaml
group:
  prefix: api/v1
  middleware:
    - "api"
  namespace: App\Http\Controllers\Api
  paths:
    "users":
      get:
        uses: UserController@index
        
      post:
        uses: UserController@store
        
    "users/{id}":
      get:
        uses: UserController@find
        
      put:
        uses: UserController@update
```

## Default route names

If you don't provide an `as` option in your route definition:

```yaml
    "users/{id}":
      get:
        uses: UserController@find
        as: my_user_find_route_name
```

Autoroute will generate a default one based on the current namespace, controller and action names: `api.user.find`.

### Custom default route name

If you're not happy with the default route name format, you can implement your own `Eyf\Autoroute\NamerInterface` and bind it accordingly in your Laravel app service provider:

```php
// app/Providers/AppServiceProvider.php

use Eyf\Autoroute\NamerInterface;
use App\Services\MyRouteNamer;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(NamerInterface::class, MyRouteNamer::class);
    }
}
```
