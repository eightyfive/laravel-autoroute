<?php
namespace Eyf\Autoroute;

use Illuminate\Routing\Router;
use Illuminate\Database\Eloquent\Collection;
use cebe\openapi\Reader;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;

class Autoroute
{
    const METHOD_CREATE = "POST";
    const METHOD_READ = "GET";
    const METHOD_UPDATE = "PUT";
    const METHOD_DELETE = "DELETE";
    const METHOD_LIST = "GET";

    const ACTION_CREATE = "create";
    const ACTION_READ = "read";
    const ACTION_UPDATE = "update";
    const ACTION_DELETE = "delete";
    const ACTION_LIST = "list";

    protected $groups = [];
    protected $router;
    protected $resolver;

    public function __construct(
        Router $router,
        AutorouteResolverInterface $resolver,
        string $dir = null
    ) {
        $this->router = $router;
        $this->resolver = $resolver;

        // TODO: Default `dir` = "./public/api.yaml"
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

    public function getRequest(string $routeId, string $method)
    {
        [$spec, $uri] = $this->parseRouteId($routeId);

        $operation = $this->findOperation($spec, $uri, $method);

        if ($operation->requestBody === null) {
            return [];
        }

        if ($operation->requestBody->content === null) {
            return [];
        }

        $mediaType = $operation->requestBody->content["application/json"];

        if (!$mediaType) {
            return [];
        }

        $rules = [];
        $schema = $mediaType->schema;
        $requiredNames = $schema->required ?? [];

        foreach ($requiredNames as $name) {
            $rules[$name] = ["required"];
        }

        if ($schema->type !== "object") {
            throw new AutorouteException(
                "Request body type not supported: " . $schema->type
            );
        }

        foreach ($schema->properties as $name => $property) {
            if (!isset($rules[$name])) {
                $rules[$name] = [];
            }

            // https://swagger.io/docs/specification/data-models/data-types/

            if ($property->type === "array") {
                throw new AutorouteException(
                    "Validation type not supported: array"
                );
            }

            if ($property->type === "object") {
                throw new AutorouteException(
                    "Validation type not supported: object"
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

    protected function findOperation(
        OpenApi $spec,
        string $uri,
        string $method
    ): Operation {
        $verb = strtolower($method);

        $pathItem = $this->findPathItem($spec, $uri);

        if ($pathItem && isset($pathItem->{$verb})) {
            return $pathItem->{$verb};
        }

        throw new AutorouteException(
            "Operation not found: " . $uri . " (" . $verb . ")"
        );
    }

    protected function findPathItem(OpenApi $spec, string $uri): PathItem
    {
        foreach ($spec->paths as $pathUri => $pathItem) {
            if ($pathUri === $uri) {
                return $pathItem;
            }
        }

        throw new AutorouteException("PathItem not found: " . $uri);
    }

    protected function parseRouteId(string $routeId)
    {
        $segments = explode("/", $routeId);

        $prefix = array_shift($segments);

        $group = $this->getGroup($prefix);

        return [$group["spec"], "/" . implode("/", $segments)];
    }

    protected function getPrefixFromFileName(string $fileName)
    {
        return pathinfo($fileName, PATHINFO_FILENAME);
    }

    protected function addGroup(string $prefix, OpenApi $spec, array $options)
    {
        $this->groups[$prefix] = compact("spec", "options");
    }

    protected function getGroup(string $prefix)
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
                $operation->operationId ??
                $this->resolver->getOperationId($uri, $method);

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

    public function isSecured(string $routeId, string $method): bool
    {
        [$spec, $uri] = $this->parseRouteId($routeId);

        $operation = $this->findOperation($spec, $uri, $method);

        $isSecured = $spec->security !== null && count($spec->security) > 0;

        // TODO: Check if security applied/disabled at upper levels: PathItem, etc...
        if ($operation->security !== null) {
            $isSecured = count($operation->security) > 0;
        }

        return $isSecured;
    }

    public function authorize(
        string $action,
        string $routeId,
        array $parameters
    ) {
        [, $uri] = $this->parseRouteId($routeId);

        $models = $this->resolver->getRouteModels($uri, $parameters);

        $name = $this->resolver->getAbilityName($uri, $action);
        $args = $this->getAbilityArgs($models, $uri);

        return [$name, $args];
    }

    protected function getAbilityArgs(Collection $models, string $uri)
    {
        $args = $models
            ->reverse()
            ->values()
            ->all(); // Array

        $modelName = $this->resolver->getRouteModelName($uri);

        if ($modelName) {
            // Ex: `/users`
            // $this->authorize('list', App\Models\User::class);

            // Ex: `/users/123/comments`
            // $this->authorize('listUser', App\Models\Comment::class, $user);

            array_unshift($args, $modelName);
        }

        return $args;
    }

    public function queryByRoute(
        string $action,
        string $routeId,
        array $parameters
    ) {
        [, $uri] = $this->parseRouteId($routeId);

        if ($action === static::ACTION_READ) {
            return $this->resolver->readByRoute($uri, $parameters);
        }

        if ($action === static::ACTION_LIST) {
            return $this->resolver->listByRoute($uri, $parameters);
        }

        throw new AutorouteException("Unsupported query action: " . $action);
    }

    public function mutateByRoute(
        string $action,
        string $routeId,
        array $parameters,
        array $data
    ) {
        [, $uri] = $this->parseRouteId($routeId);

        if ($action === static::ACTION_CREATE) {
            return $this->resolver->createByRoute($uri, $parameters, $data);
        }

        if ($action === static::ACTION_UPDATE) {
            return $this->resolver->updateByRoute($uri, $parameters, $data);
        }

        if ($action === static::ACTION_DELETE) {
            return $this->resolver->deleteByRoute($uri, $parameters);
        }

        throw new AutorouteException("Unsupported mutate action: " . $action);
    }
}
