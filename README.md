# laravel-autoroute
## Introduction
Autoroute is a simple helper for registering Laravel routes in a more concise way.

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
Declare your routes in `config/autoroute.php`:
```php
    'routes' => [
        [null, 'index.contact'],
        [null, 'index.homePage', 'home'],
        ['/login', 'auth.login'],
        ['/login', 'auth.login', 'POST']
    ]
```

Then register them as normal in `app/Http/routes.php`:
```php
Route::group(['middleware' => ['web']], function () {
    app('autoroute')->make();
    ...
```

## Routes

Each route is represented by an array of this form:
```php
    [$url, $ctrl, $verb, $name]
```
You can omit `$verb` and pass a custom route `$name` directly instead:
```php
    [$url, $ctrl, $name]
```

_Notes_:

1. `$url` is always required. Pass `null` if you want Autoroute to auto-generate the url based on `$ctrl`
2. `index` keyword in `$ctrl` is ignored by default (See examples & "Options")
3. **Caveat**: if you don't pass `$verb`, but do pass a custom route `$name` of yours as the third parameter, make sure this `$name` is not any of the HTTP verbs nor the `any` keyword.

### Controller format
`$ctrl` variable is of form: `{ctrl}.{action}`. Ex: `index.contact`.

**Generated controller string**

Behind the scene it will be transformed into the normal Laravel controller string: `IndexController@contact`.

**Generated route name**

Autoroute will also generate a default route _name_ for you if not passed: `index.contact`.

**Note**: All of this is configurable. See "Options".

## Constraints
Constraints are used to match [route parameters](https://laravel.com/docs/5.2/routing#route-parameters) against regular expressions.

**Example**
```php
    'constraints' => [
        'id' => '\d+',
        'hotel_name' => '[\w-]+',
        ...
    ]
```
**Note**: Every route parameter _must_ have a constraint defined. If not Autoroute will throws an `Exception`.


## Examples

**All examples illustrate the default options**. See "Options" for alternatives.

### Simplest route
```php
    [null, 'index.contact']
```
Will generate:
- `IndexController@contact` (controller)
- `index.contact` (route name)
- `get` (verb)
- `/contact` (url) - `index` has been ignored

### Route with custom `url`
```php
    ['/contact-us', 'index.contact']
```
Will generate:
- Same as above
- `/contact-us`

### Route with custom `name`
```php
    [null, 'index.contact', 'contact_us']
```
Will generate:
- Same as first example
- But with `contact_us` as route name

### `POST` route
```php
    [null, 'auth.login', 'POST']
```
Will generate:
- `AuthController@login`
- `auth.login`
- `post`
- `/auth/login`


### `match` route
```php
    [null, 'auth.login', ['get', 'POST']]
```
Will generate:
- `AuthController@login`
- `auth.login`
- `get`, `post`
- `/auth/login`

### Route with namespace
```php
    [null, 'auth.auth.register']
```
Will generate:
- `Auth\\AuthController@login`
- `auth.register` (namespace has been ignored)
- `get`
- `/auth/register`  (namespace has been ignored)

### Route with camelCase `ctrl`
```php
    [null, 'auth.facebookCallback']
```
Will generate:
- `AuthController@facebookCallback`
- `auth.facebook-callback`
- `get`
- `/auth/facebook-callback`

```php
    [null, 'userAccount.myProfile']
```
Will generate:
- `UserAccountController@myProfile`
- `user-account.my-profile`
- `get`
- `/user-account/my-profile`

## Options
### `ignore_index` option
Whethers or not to ignore `index` keyword when generating `url`.

**Default**
```php
    'ingnore_index' => true,
```
- `index.contact` > `/contact`
- `auth/index` > `/auth`

**Example**
```php
    'ingnore_index' => false,
```
- `index.contact` gives `/index/contact` url
- `auth.index` gives `/auth/index` url

### `separator` option
You can specify the separtor to use in order to [`explode`](http://php.net/manual/en/function.explode.php) the `$ctrl` string.

**Default**
```php
    'separator' => '.',
```
- `index.contact`

**Example**
```php
    'separator' => '->',
```
- `index->contact`

### `route_name` option
The `route_name` option tells how to format the _route name_.

**Default**
```php
    'route_name' => '{ctrl}.{action}',
```
- `auth.login` gives `auth/login` route name

**Example**
```php
    'separator' => '>',
    'route_name' => '{ctrl}/{action}',
```
- `auth>login` gives `auth/login` route name

### `filters` options
The `filters` option holds a set of filters to apply to both `$ctrl` and `$action` strings.

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
- `userAccount.myProfile` gives `/user-account/my-profile` url

**Example**
```php
    'separator' => '~'
    'route_name' => '{ctrl}--{action}',
    'filters' => 'snake'
```
- `userAccount~myProfile` gives `user_account--my_profile` route name
- `userAccount~myProfile` gives `/user_account/my_profile` url

