# laravel-autoroute

Autoroute helps you register Laravel routes as YAML.

> "La route? Là où on va, on a pas besoin... De route."

## Install

```
composer require eyf/laravel-autoroute
```

## Usage

```php
<?php
// app/Providers/RouteServiceProvider.php

use Eyf\Autoroute\Autoroute;

class RouteServiceProvider extends ServiceProvider
{
    public function map(Autoroute $autoroute)
    {
        $autoroute->load(["api.yaml"]);
    }
}
```

**Note**: It will automatically look for files inside the Laravel `routes/` folder.

### Sample `api.yaml`

```yaml
domain: api.example.org
prefix: v1
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

Or using the [compact syntax](#uses-compact-syntax):

```yaml
domain: api.example.org
prefix: v1
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

### Template parameters

Autoroute supports light parameters in YAML files. The format is `%<parameter_name>%`.

For instance let's say you need to work with a local API subdomain:

```php
<?php
// app/Providers/RouteServiceProvider.php

use Eyf\Autoroute\Autoroute;

class RouteServiceProvider extends ServiceProvider
{
    public function map(Autoroute $autoroute)
    {
        $parameters = [
            "app_domain" => env("APP_DOMAIN", "example.org"),
        ];

        $autoroute->load(["api.yaml"], $parameters);
    }
}
```

And in your local `.env` file:

```env
APP_DOMAIN=localhost:8000
# APP_DOMAIN=example.org # PROD
```

And in your `api.yaml` file:

```yaml
domain: api.%app_domain%
prefix: v1
# ...
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
        # as: api.user.find (generated)
```

### Custom default route name

If you're not happy with the default route name format, you can implement your own `Eyf\Autoroute\RouteNamerInterface` and bind it accordingly in your Laravel app service provider:

```php
<?php
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
domain: api.%app_domain%
prefix: v1
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
