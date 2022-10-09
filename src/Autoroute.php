<?php
namespace Eyf\Autoroute;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Illuminate\Contracts\Auth\Access\Gate;
// use Symfony\Component\Yaml\Yaml;
use cebe\openapi\Reader;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Paths;
use cebe\openapi\spec\OpenApi;
// use cebe\openapi\spec\Operation;

class Autoroute
{
    const METHOD_CREATE = "post";
    const METHOD_READ = "get";
    const METHOD_UPDATE = "put";
    const METHOD_DELETE = "delete";

    const ACTION_CREATE = "create";
    const ACTION_READ = "read";
    const ACTION_UPDATE = "update";
    const ACTION_DELETE = "delete";
    const ACTION_LIST = "list";

    protected $gate;
    protected $namer;
    protected $router;

    public function __construct(
        Router $router,
        Gate $gate,
        RouteNamerInterface $namer,
        string $dir = null
    ) {
        $this->gate = $gate;
        $this->namer = $namer;
        $this->router = $router;
        $this->dir = $dir;
    }

    public function createGroup(array $group, string $fileName)
    {
        if ($this->dir) {
            $fileName = "{$this->dir}/{$fileName}";
        }

        $spec = Reader::readFromYamlFile(realpath($fileName));

        $this->router->group($group, function () use ($spec) {
            $this->createRoutes($spec);
        });
    }

    public function createRoutes(OpenApi $spec)
    {
        foreach ($spec->paths as $uri => $path) {
            $this->createRoute($uri, $path, $spec);
        }
    }

    protected function createRoute(string $uri, PathItem $path, OpenApi $spec)
    {
        foreach ($path->getOperations() as $method => $operation) {
            $uses =
                $operation->operationId ?? $this->getOperationId($uri, $method);

            // Create route
            $route = call_user_func(
                [$this->router, $method],
                $uri,
                compact("uses")
            );

            $securities = $operation->security ?? $spec->security;

            foreach ($securities as $security) {
                if (isset($security->Sanctum)) {
                    $route->middleware("auth:sanctum");
                }
            }
        }
    }

    protected function getOperationId(string $uri, string $method)
    {
        $segments = explode("/", ltrim($uri, "/"));

        if ($method === static::METHOD_READ) {
            $action =
                count($segments) % 2 === 0
                    ? static::ACTION_READ
                    : static::ACTION_LIST;
        } elseif ($method === static::METHOD_CREATE) {
            $action = static::ACTION_CREATE;
        } elseif ($method === static::METHOD_UPDATE) {
            $action = static::ACTION_UPDATE;
        } elseif ($method === static::METHOD_DELETE) {
            $action = static::ACTION_DELETE;
        } else {
            throw new \Exception("Autoroute: Method not supported (PATCH)");
        }

        return "\\Eyf\\Autoroute\\Http\Controllers\\ResourceController@" .
            $action;
    }

    public function create(array $routes)
    {
        foreach ($routes as $path => $route) {
            if (isset($route["where"])) {
                $constraints = $route["where"];

                unset($route["where"]);
            } else {
                $constraints = [];
            }

            $this->__createRoute($path, $route, $constraints);
        }
    }

    protected function __createRoute(
        string $path,
        array $verbs,
        array $constraints = []
    ) {
        foreach ($verbs as $method => $options) {
            if (is_string($options)) {
                $uses = $this->namer->getUses($options);
                $options = compact("uses");
            } else {
                $uses = $options["uses"];
            }

            // Create route
            $route = call_user_func([$this->router, $method], $path, $options);

            // Add parameter constraints
            foreach ($constraints as $param => $constraint) {
                $route->where($param, $constraint);
            }

            // Default route name
            if (!isset($options["as"])) {
                $group = last($this->router->getGroupStack());

                $name = $this->namer->getRouteName(
                    $uses,
                    $group ? $group["namespace"] : ""
                );

                $route->name($name);
            }
        }
    }

    public function authorizeRequest(string $action, Request $request)
    {
        $modelNames = $this->getModelNames($request->route()->uri);
        $parameters = $request->route()->parameters();

        $models = [];
        $index = 0;

        foreach ($parameters as $modelId) {
            $model = $this->findModel($modelNames[$index], $modelId);

            if ($model === null) {
                throw new NotFoundHttpException("Not Found");
            }

            array_push($models, $model);
            $index++;
        }

        $abilityArgs = $models;

        if (count($parameters) < count($modelNames)) {
            $modelName = end($modelNames);

            // Ex: `/users/123/comments`

            // $this->authorize($ability, Comment::class, $user);
            array_unshift($abilityArgs, $modelName);

            // [$user, Comment::class];
            array_push($models, $modelName);
        }

        $abilityName = $this->getAbilityName($request->route()->uri, $action);

        // TODO: Run authorization only when:
        // `$gate->policies[$modelName . "Policy"]` is set?
        // Or keep strong "Unauthorized" by default?
        $this->gate->authorize($abilityName, $abilityArgs);

        return $models;
    }

    public function createModel(string $modelName, array $data)
    {
        return call_user_func([$modelName, "create"], $data);
    }

    public function getModels(string $modelName)
    {
        // TODO: Filter by relationship
        // Ex: /users/123/comments
        // Comments of User 123 only...
        return call_user_func([$modelName, "all"]);
    }

    protected function findModel(string $modelName, string $id)
    {
        return call_user_func([$modelName, "find"], $id);
    }

    protected function getModelBaseNames(string $routeUri)
    {
        // TODO: api/
        $uri = str_replace("api/", "", $routeUri);

        $segments = explode("/", $uri);
        $segments = array_filter($segments, function ($segment) {
            // https://www.php.net/manual/en/function.preg-match.php
            return preg_match("/\{[a-z_-]+\}/i", $segment) === 0;
        });

        $modelBaseNames = array_map(function ($segment) {
            return $this->getModelBaseName($segment);
        }, $segments);

        return array_values($modelBaseNames);
    }

    protected function getModelBaseName(string $segment)
    {
        return Str::ucfirst(Str::singular($segment));
    }

    protected function getAbilityName(string $uri, string $action)
    {
        $modelBaseNames = $this->getModelBaseNames($uri);

        if (count($modelBaseNames) === 1) {
            return $action;
        }

        array_pop($modelBaseNames);

        return $action . implode("", $modelBaseNames);
    }

    public function getAbilityArgs(string $uri, array $parameters)
    {
        $modelNames = $this->getModelNames($uri);

        $args = $this->getRouteModels($uri, $parameters);

        if (count($parameters) < count($modelNames)) {
            // Ex: `/users/123/comments`

            // $this->authorize($ability, Comment::class, $user);
            array_unshift($args, end($modelNames));
        }

        return $args;
    }

    public function getAuthorizeArgs(
        string $uri,
        array $parameters,
        string $action
    ) {
        $name = $this->getAbilityName($uri, $action);
        $args = $this->getAbilityArgs($uri, $parameters);

        return [$name, $args];
    }

    public function getRouteModels(string $uri, array $parameters)
    {
        $modelNames = $this->getModelNames($uri);

        $models = [];
        $index = 0;

        foreach ($parameters as $modelId) {
            $model = $this->findModel($modelNames[$index], $modelId);

            if ($model === null) {
                throw new NotFoundHttpException("Not Found");
            }

            array_push($models, $model);
            $index++;
        }

        if (count($parameters) < count($modelNames)) {
            // Ex: `/users/123/comments`

            // [$user, Comment::class];
            array_push($models, end($modelNames));
        }

        return $models;
    }

    protected function getModelNames(string $uri)
    {
        $modelBaseNames = $this->getModelBaseNames($uri);

        return array_map(function ($modelBaseName) {
            return $this->getModelsNamespace() . "\\" . $modelBaseName;
        }, $modelBaseNames);
    }

    protected function getModelsNamespace()
    {
        // TODO
        return "App\\Models";
    }
}
