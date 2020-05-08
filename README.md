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

use Eyf\Autoroute\Autoroute;

class RouteServiceProvider extends ServiceProvider
{
    public function map(Autoroute $autoroute)
    {
        $autoroute->create([
            'users/{id}' => [
                'where' => [
                    'id' => '[0-9]+',
                ],
                'get' => [
                    'uses' => 'UserController@get',
                ],
            ],
        ]);
    }
}
```

### Loading files (yaml, json, php...)

Obviously you will want to put your routes in some kind of files. Use the `load` method for that.

```php
class RouteServiceProvider extends ServiceProvider
{
    public function map(Autoroute $autoroute)
    {
        $autoroute->load('api.yaml', 'web.yaml');
    }
}
```

__Notes__:
- It will look for files inside the Laravel `routes/` folder.
- Supports all file types supported by [hassankhan/config](https://github.com/hassankhan/config).
- You need to `composer require symfony/yaml` if you choose to use YAML files.

### Sample `api.yaml`

```yaml
group:
  prefix: api/v1
  middleware:
    - api
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

Autoroute will generate a default route name based on the current namespace, controller and action names:

```yaml
    "users/{id}":
      get:
        uses: UserController@find
        # as: api.user.find (Generated)
```

### Custom default route name

If you're not happy with the default route name format, you can implement your own `Eyf\Autoroute\RouteNamerInterface` and bind it accordingly in your Laravel app service provider:

```php
// app/Providers/AppServiceProvider.php

use Eyf\Autoroute\RouteNamerInterface;
use App\Services\MyRouteNamer;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(RouteNamerInterface::class, MyRouteNamer::class);
    }
}
```

## `uses` compact syntax

If you're not using any route options (`as`, etc...), you can use a "compact" syntax to specify your controllers:

```yaml
group:
  prefix: api/v1
  middleware:
    - api
  namespace: App\Http\Controllers\Api
  paths:
    "users":
      get: user.index
      post: user.store

    "users/{id}":
      get: user.find
      put: user.update
```

### Custom compact syntax

You can customize the shorthand syntax by implementing `RouteNamerInterface::getUses(string $compact)`.
