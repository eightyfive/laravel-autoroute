<?php
namespace Eyf\Autoroute;

use Illuminate\Routing\Router;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use cebe\openapi\Reader;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Schema;

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

        $filePath = realpath($fileName);

        if (!file_exists($filePath)) {
            throw new AutorouteException("File not found: " . $filePath);
        }

        $spec = Reader::readFromYamlFile($filePath);

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

        $mediaType = $operation->requestBody->content["application/json"];

        if (!$mediaType) {
            // Validate nothing
            return [];
        }

        return $this->schemaToRules($mediaType->schema);
    }

    protected function schemaToRules(Schema $schema): array
    {
        if ($schema->type !== "object") {
            throw new AutorouteException(
                "Unsupported schema type: " . $schema->type
            );
        }

        $rules = [];
        $requiredNames = $schema->required ?? [];

        foreach ($requiredNames as $name) {
            $rules[$name] = ["required"];
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

    public function toModelResponse(
        string $routeId,
        string $action,
        Model $model
    ) {
        if ($action === static::ACTION_CREATE) {
            return $this->getModelResponse(
                $routeId,
                static::METHOD_CREATE,
                $model,
                [201, 204, 200]
            );
        }

        if ($action === static::ACTION_READ) {
            return $this->getModelResponse(
                $routeId,
                static::METHOD_READ,
                $model,
                [200]
            );
        }

        if ($action === static::ACTION_UPDATE) {
            return $this->getModelResponse(
                $routeId,
                static::METHOD_UPDATE,
                $model,
                [200]
            );
        }

        if ($action === static::ACTION_DELETE) {
            return $this->getModelResponse(
                $routeId,
                static::METHOD_DELETE,
                $model,
                [204, 200]
            );
        }

        throw new AutorouteException("Unsupported action: " . $action);
    }

    public function toModelsResponse(
        string $routeId,
        string $action,
        Collection $models
    ) {
        if ($action === static::ACTION_LIST) {
            return $this->getModelsResponse(
                $routeId,
                static::METHOD_LIST,
                $models,
                [200]
            );
        }

        throw new AutorouteException("Unsupported action: " . $action);
    }

    protected function getModelResponse(
        string $routeId,
        string $method,
        Model $model,
        array $statuses
    ) {
        [$status, $schema] = $this->getResponseSchema(
            $routeId,
            $method,
            $statuses
        );

        return $this->resolver->toModelResponse($status, $schema, $model);
    }

    protected function getModelsResponse(
        string $routeId,
        string $method,
        Collection $models,
        array $statuses
    ) {
        [$status, $schema] = $this->getResponseSchema(
            $routeId,
            $method,
            $statuses
        );

        return $this->resolver->toModelsResponse($status, $schema, $models);
    }

    protected function getResponseSchema(
        string $routeId,
        string $method,
        array $statuses
    ): array {
        [$spec, $uri] = $this->parseRouteId($routeId);

        $operation = $this->findOperation($spec, $uri, $method);

        $status = $this->findResponseStatus($operation, $statuses);

        $res = $operation->responses[strval($status)];

        if (!$res->content) {
            return [$status, null];
        }

        if (!$res->content["application/json"]) {
            throw new AutorouteException("Unsupported response content type");
        }

        $schema = $res->content["application/json"]->schema;

        if (!in_array($schema->type, ["object", "array"])) {
            throw new AutorouteException(
                "Unsupported schema type: " . $schema->type
            );
        }

        if ($schema->type === "array") {
            $schema = $this->schemaToArray($schema->items);
        } else {
            $schema = $this->schemaToArray($schema);
        }

        return [$status, $schema];
    }

    protected function findResponseStatus(
        Operation $operation,
        array $statuses
    ): int {
        foreach ($statuses as $status) {
            $res = $operation->responses[strval($status)];

            if ($res) {
                return $status;
            }
        }

        throw new AutorouteException(
            "No response found: " . implode(", ", $statuses)
        );
    }

    protected function schemaToArray(Schema $schema)
    {
        $data = [];

        foreach ($schema->properties as $name => $value) {
            $data[$name] = $value;
        }

        return $data;
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

            // Add middlewares
            $securities = $operation->security ?? $spec->security;

            foreach ($securities as $security) {
                $middlewares = $security->getSerializableData();

                foreach ($middlewares as $middleware => $config) {
                    if (count($config)) {
                        $route->middleware(
                            $middleware . ":" . implode(",", $config)
                        );
                    } else {
                        $route->middleware($middleware);
                    }
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

            // Ex: `/users/123/posts`
            // $this->authorize('listUser', App\Models\Post::class, $user);

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
