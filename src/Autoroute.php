<?php
namespace Eyf\Autoroute;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
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

    protected string|null $dir;
    protected Router $router;
    protected AutorouteResolverInterface $resolver;
    protected OpenApi|null $spec;
    protected string|null $prefix;
    protected $service;

    public function __construct(
        Router $router,
        AutorouteResolverInterface $resolver,
        string $dir = null
    ) {
        $this->spec = null;
        $this->router = $router;
        $this->resolver = $resolver;
        $this->dir = $dir;
    }

    public function createGroup(
        string $fileName,
        array $options = [],
        $service = null
    ) {
        if ($this->dir) {
            $fileName = "{$this->dir}/{$fileName}";
        }

        $filePath = realpath($fileName);

        if (!file_exists($filePath)) {
            throw new AutorouteException("File not found: " . $fileName);
        }

        $this->spec = Reader::readFromYamlFile($filePath);
        $this->prefix = $options["prefix"] ?? null;
        $this->service = $service;

        $this->router->group($options, function () {
            $this->createRoutes($this->spec);
        });
    }

    public function getValidationRules(Request $request)
    {
        $route = $request->route();

        return $this->getValidationRulesByRoute(
            $route->uri,
            $request->method()
        );
    }

    protected function getValidationRulesByRoute(
        string $routeId,
        string $method
    ) {
        $uri = $this->getUri($routeId);

        $operation = $this->findOperation($this->spec, $uri, $method);

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
                $typeRule = "array";
            } elseif ($property->type === "object") {
                $typeRule =
                    "array:" . implode(",", array_keys($property->properties));
            } else {
                $typeRule =
                    $property->type === "number" ? "numeric" : $property->type;
            }

            array_push($rules[$name], $typeRule);

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
                [204, 200]
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

    public function getComponentResource(
        string $componentName,
        Model $model
    ): JsonResource {
        $schema = $this->getComponentSchema($this->spec, $componentName);

        return $this->resolver->toModelResource($model, $schema);
    }

    protected function getComponentSchema(
        OpenApi $spec,
        string $componentName
    ): array {
        $component = $spec->components->schemas[$componentName];

        return $this->schemaToArray($spec, $component);
    }

    protected function getResponseSchema(
        string $routeId,
        string $method,
        array $statuses
    ): array {
        $uri = $this->getUri($routeId);

        $operation = $this->findOperation($this->spec, $uri, $method);

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
            $schema = $this->schemaToArray($this->spec, $schema->items);
        } else {
            $schema = $this->schemaToArray($this->spec, $schema);
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

    protected function schemaToArray(OpenApi $spec, Schema $object, $data = [])
    {
        foreach ($object->properties as $name => $property) {
            if ($property->type === "array") {
                if (isset($property->items->allOf)) {
                    $data[$name] = $this->allOfToArray(
                        $spec,
                        $property->items->allOf
                    );
                } else {
                    $data[$name] = $this->schemaToArray(
                        $spec,
                        $property->items
                    );
                }
            } elseif (isset($property->allOff)) {
                $data[$name] = $this->allOfToArray($spec, $property->allOf);
            } elseif ($property->type === "object") {
                $data[$name] = $this->schemaToArray($spec, $property);
            } else {
                $data[$name] = $property->type;
            }
        }

        return $data;
    }

    protected function allOfToArray(OpenApi $spec, array $allOf)
    {
        $data = [];

        // `allOf` children are `object`
        foreach ($allOf as $object) {
            $data = array_merge($data, $this->schemaToArray($spec, $object));
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

    protected function getUri(string $routeId)
    {
        if ($this->prefix) {
            $segments = explode("/", $routeId);

            // Remove prefix from URI identifier
            array_shift($segments);

            return "/" . implode("/", $segments);
        }

        return "/" . $routeId;
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
            if (!isset($operation->operationId)) {
                throw new AutorouteException(
                    "Operation ID not found: " . $uri . "(" . $method . ")"
                );
            }

            $uses = $this->resolver->getControllerString(
                $operation->operationId,
                $uri,
                $method
            );

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

    public function callOperation(Request $request)
    {
        $route = $request->route();

        $uri = $this->getUri($route->uri);

        $operation = $this->findOperation(
            $this->spec,
            $uri,
            $request->method()
        );

        return $this->resolver->callOperation(
            $operation->operationId,
            $route,
            $request,
            $this->service
        );
    }
}
