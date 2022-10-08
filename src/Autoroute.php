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

    public function load(array $files, array $parameters = [])
    {
        if ($this->dir) {
            $files = array_map(function ($filename) {
                return "{$this->dir}/{$filename}";
            }, $files);
        }

        foreach ($files as $file) {
            $spec = Reader::readFromYamlFile(realpath($file));

            $this->router->group(
                [
                    "prefix" => "api",
                    "namespace" => "App\\Http\\Controllers\\Api",
                ],
                function () use ($spec) {
                    $this->createRoutes($spec);
                }
            );
        }
    }

    public function createRoutes(OpenApi $spec)
    {
        foreach ($spec->paths as $pathName => $path) {
            $this->createRoute($pathName, $path, $spec);

            // if (isset($route["where"])) {
            //     $constraints = $route["where"];

            //     unset($route["where"]);
            // } else {
            //     $constraints = [];
            // }
        }
    }

    protected function createRoute(
        string $pathName,
        PathItem $path,
        OpenApi $spec
    ) {
        foreach ($path->getOperations() as $verb => $operation) {
            $uses =
                $operation->operationId ??
                $this->getOperationId($pathName, $verb);

            // Create route
            $route = call_user_func(
                [$this->router, $verb],
                $pathName,
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

    protected function getOperationId(string $pathName, string $method)
    {
        $segments = explode("/", ltrim($pathName, "/"));

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
        foreach ($verbs as $verb => $options) {
            if (is_string($options)) {
                $uses = $this->namer->getUses($options);
                $options = compact("uses");
            } else {
                $uses = $options["uses"];
            }

            // Create route
            $route = call_user_func([$this->router, $verb], $path, $options);

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
        $modelBaseNames = $this->getModelBaseNames($request);
        $modelNames = $this->getModelNames($modelBaseNames);
        $modelIds = $request->route()->parameters();

        $models = [];
        $index = 0;

        foreach ($modelIds as $modelId) {
            $model = $this->findModel($modelNames[$index], $modelId);

            if ($model === null) {
                throw new NotFoundHttpException("Not Found");
            }

            array_push($models, $model);
            $index++;
        }

        $abilityArgs = $models;

        if (count($modelIds) < count($modelNames)) {
            $modelName = end($modelNames);

            // Ex: `/users/123/comments`

            // $this->authorize($ability, Comment::class, $user);
            array_unshift($abilityArgs, $modelName);

            // [$user, Comment::class];
            array_push($models, $modelName);
        }

        $abilityName = $this->getAbilityName($action, $modelBaseNames);

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

    protected function getModelBaseNames(Request $request)
    {
        // TODO: api/
        $uri = str_replace("api/", "", $request->route()->uri);

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

    protected function getModelIds(Request $request)
    {
        $parameters = $request->route()->parameters();

        return array_values($parameters);
    }

    protected function getAbilityName(string $action, array $modelBaseNames)
    {
        if (count($modelBaseNames) === 1) {
            return $action;
        }

        array_pop($modelBaseNames);

        return $action . implode("", $modelBaseNames);
    }

    protected function getModelNames(array $modelBaseNames)
    {
        return array_map(function ($modelBaseName) {
            // TODO: return $this->autoroute->getModelsNamespace() . '\\' ...;
            return "App\\Models\\" . $modelBaseName;
        }, $modelBaseNames);
    }
}
