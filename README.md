# laravel-autoroute
## Introduction
Autoroute is a simple helper for registering Laravel routes in a more concise way.

"La route? Là où on va on a pas besoin.. De route."

### Install
```
composer require eightyfive/laravel-autoroute
```

Then add the Service Provider in your `config/app.php` file:

```php
    Eyf\AutorouteServiceProvider::class,
```

Then run `php artisan vendor:publish` in order to copy `autoroute.php` config in your project.

### State of the art
Register your routes as normal in `app/Http/routes.php`:

```php
Route::group(['middleware' => ['web']], function () {
    app('autoroute')->make([
        ['index.contact'],
        ['index.homePage', 'home'], // custom route name
        ['auth.login' => '/login', 'login'], // custom pathname & route name
        ['auth.login' => '/login', 'POST'] // POST request...
    ]);
    ...
```

## Routes

Each route is represented by an array of this form:
```php
    [$ctrl, $verb, $name]
```

You can omit the `$verb` and pass directly a custom route `$name` instead:
```php
    [$ctrl, $name]
```

In order to specify a custom pathname and bypass default Autoroute pathname generation, pass the `$ctrl` parameter as key / value:
```
    [$ctrl => $pathname, $verb, $name]
    [$ctrl => $pathname, $verb]
    [$ctrl => $pathname, $name]
    [$ctrl => $pathname]
```

_Notes_:
- **Caveat**: if you don't pass `$verb`, but do pass a custom route `$name` instead, make sure this `$name` is not any of the HTTP verbs nor the `any` keyword.

### `$ctrl` format
`$ctrl` parameter is a string of form: `{controller}.{action}`.

Based on that string, Autoroute will generate the normal Laravel controller string and, if not passed, it will also generate a default route name & pathname for you.

- Ex: `$ctrl = 'user.profile'`
- `UserController@profile` – Laravel controller string
- `user.profile` – Default route name
- `user/profile` – Default pathname

_Notes_:

- `index` keyword in `$ctrl` is ignored by default (See [examples](#examples) & [options](#options))

**All of this is configurable.** See [options](#options).

## Constraints
Constraints are used to match [route parameters](https://laravel.com/docs/5.2/routing#route-parameters) against regular expressions.

**Example**
```php
    'constraints' => [
        'id' => '\d+',
        'username' => '[\w-]+',
        ...
    ]
```
__Note__: Every route parameter _must_ have a constraint defined. If not Autoroute will throws an `Exception`.


## Examples

**All examples illustrate the default options**. See [options](#options) for alternatives.

### Simplest route
```php
    ['index.contact']
```
Will generate:
- `IndexController@contact` (controller)
- `index.contact` (route name)
- `get` (verb)
- `contact` (pathname) - `index` has been ignored

### Route with custom `pathname`
```php
    ['index.contact' => '/contact-us']
```
Will generate:
- Same as above
- But with `contact-us` as pathname

### Route with custom `name`
```php
    ['index.contact', 'contact_us']
```
Will generate:
- Same as first example
- But with `contact_us` as route name

### `POST` route
```php
    ['auth.login', 'POST']
```
Will generate:
- `AuthController@login`
- `auth.login` (route name)
- `post`
- `auth/login`


### `match` route
```php
    ['auth.login', ['get', 'POST']]
```
Will generate:
- `AuthController@login`
- `auth.login`
- `get`, `post`
- `auth/login`

### Route with namespace
```php
    ['auth.auth.register']
```
Will generate:
- `Auth\\AuthController@login`
- `auth.auth.register`
- `get`
- `auth/auth/register`

In order to avoid `auth` keyword repetition:
```php
    ['auth.auth.register' => 'auth/register', 'auth.register']
```

### Route with camelCase `ctrl`
```php
    ['auth.facebookCallback']
```
Will generate:
- `AuthController@facebookCallback`
- `auth.facebook-callback` (route name)
- `get`
- `auth/facebook-callback`

```php
    ['userAccount.myProfile']
```
Will generate:
- `UserAccountController@myProfile`
- `user-account.my-profile` (route name)
- `get`
- `user-account/my-profile`

## Options
### `ignore_index` option
Whether or not to ignore the `index` keyword when generating `pathname`.

**Default**
```php
    'ignore_index' => true,
```
- `index.contact` gives `contact` pathname
- `auth.index` gives `auth` pathname

**Example**
```php
    'ignore_index' => false,
```
- `index.contact` gives `index/contact` pathname
- `auth.index` gives `auth/index` pathname

### `ctrl_separator` option
You can specify the separtor to use in order to [`explode`](http://php.net/manual/en/function.explode.php) the `$ctrl` string.

**Default**
```php
    'ctrl_separator' => '.',
```
- `index.contact`

**Example**
```php
    'ctrl_separator' => '->',
```
- `index->contact`

### `route_separator` option
You can specify the separtor to use when generating the route name.

**Default**
```php
    'ctrl_separator' => '.',
    'route_separator' => '.',
```
- `index.contact` gives `index.contact` route name

**Example**
```php
    'ctrl_separator' => '->',
    'route_separator' => '--',
```
- `index->contact` gives `index--contact` route name

### `filters` options
The `filters` option holds a set of filters to apply to every segment of a `$ctrl` string (namespace, controller, action) when generating route names.

**Possible values**
- `slug`
- `snake`
- `camel`
- Any combination: `['camel', 'snake']`, `['snake', 'slug', 'camel']`...

**Default**
```php
    'filters' => ['snake', 'slug']
```
- `userAccount.myProfile` gives `user-account.my-profile` route name
- `userAccount.myProfile` gives `user-account/my-profile` pathname

**Example**
```php
    'ctrl_separator' => '~'
    'route_separator' => '--',
    'filters' => ['snake']
```
- `userAccount~myProfile` gives `user_account--my_profile` route name
- `userAccount~myProfile` gives `user_account/my_profile` pathname

