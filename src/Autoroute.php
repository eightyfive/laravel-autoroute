<?php
namespace Eyf\Autoroute;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use cebe\openapi\Reader;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\RequestBody;

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

    protected $groups;
    protected $router;

    public function __construct(Router $router, string $dir = null)
    {
        $this->router = $router;
        $this->dir = $dir;
    }

    public function createGroup(string $fileName, array $options = [])
    {
        if ($this->dir) {
            $fileName = "{$this->dir}/{$fileName}";
        }

        if (!isset($options["prefix"])) {
            $options["prefix"] = $this->getPrefixFromFileName($fileName);
        }

        $spec = Reader::readFromYamlFile(realpath($fileName));

        $this->addGroup($options["prefix"], $spec, $options);

        $this->router->group($options, function () use ($spec) {
            $this->createRoutes($spec);
        });
    }

    public function getValidationRules(
        string $prefix,
        string $method,
        string $uri
    ) {
        $requestBody = $this->findRequestBody($prefix, $method, $uri);
        $mediaType = $requestBody->content["application/json"];

        $rules = [];

        if (!$mediaType) {
            return $rules;
        }

        $schema = $mediaType->schema;

        $requiredPropertyNames = $schema->required ?? [];

        foreach ($requiredPropertyNames as $name) {
            $rules[$name] = ["required"];
        }

        if ($schema->type !== "object") {
            // TODO: `AutorouteException`
            throw new \Exception(
                "Autoroute: only `object` type request body supported"
            );
        }

        foreach ($schema->properties as $name => $property) {
            if (!isset($rules[$name])) {
                $rules[$name] = [];
            }

            // https://swagger.io/docs/specification/data-models/data-types/

            if ($property->type === "array") {
                throw new \Exception(
                    "Autoroute: Unsupported validation type: array"
                );
            }

            if ($property->type === "object") {
                throw new \Exception(
                    "Autoroute: Unsupported validation type: object"
                );
            }

            array_push(
                $rules[$name],
                $property->type === "number" ? "numeric" : $property->type
            );

            if (isset($property->format)) {
                $formatRules = explode("|", $property->format);

                array_push($rules[$name], ...$formatRules);
            }
        }

        return $rules;
    }

    protected function findRequestBody(
        string $prefix,
        string $method,
        string $uri
    ): RequestBody|null {
        $group = $this->getGroup($prefix);

        $operation = $this->findOperation($group["spec"], $method, $uri);

        if ($operation) {
            return $operation->requestBody;
        }

        return null;
    }

    protected function findOperation(
        OpenApi $spec,
        string $method,
        string $uri
    ): Operation|null {
        $pathItem = $this->findPathItem($spec, $uri);

        if ($pathItem && isset($pathItem->{$method})) {
            return $pathItem->{$method};
        }

        return null;
    }

    protected function findPathItem(OpenApi $spec, string $uri): PathItem|null
    {
        foreach ($spec->paths as $pathUri => $pathItem) {
            if ($pathUri === $uri) {
                return $pathItem;
            }
        }

        return null;
    }

    protected function getPrefixFromFileName(string $fileName)
    {
        return pathinfo($fileName, PATHINFO_FILENAME);
    }

    protected function addGroup(string $prefix, OpenApi $spec, array $options)
    {
        $this->groups[$prefix] = compact("spec", "options");
    }

    public function getGroup(string $prefix)
    {
        return $this->groups[$prefix] ?? null;
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
        // $this->gate->authorize($abilityName, $abilityArgs);

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
        $uri = str_replace("api/", "", ltrim($routeUri, "/"));

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

        // Ex: /users/{user}/posts
        // ['User', 'Post'];

        array_pop($modelBaseNames); // pop 'Post'

        return $action . array_pop($modelBaseNames); // -> `listUser` (Posts !)
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
