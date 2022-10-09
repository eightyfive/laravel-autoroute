<?php
namespace Eyf\Autoroute;

// TODO
// use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Routing\Router;
use Illuminate\Database\Eloquent\Collection;
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
    const METHOD_LIST = "list";

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
            throw new AutorouteException("Request body type not supported");
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

    protected function findRequestBody(
        string $prefix,
        string $method,
        string $uri
    ): RequestBody {
        $group = $this->getGroup($prefix);

        $operation = $this->findOperation($group["spec"], $method, $uri);

        if ($operation) {
            return $operation->requestBody;
        }

        throw new AutorouteException(
            "RequestBody not found: " .
                $prefix .
                "/" .
                $uri .
                "(" .
                $method .
                ")"
        );
    }

    protected function findOperation(
        OpenApi $spec,
        string $method,
        string $uri
    ): Operation {
        $pathItem = $this->findPathItem($spec, $uri);

        if ($pathItem && isset($pathItem->{$method})) {
            return $pathItem->{$method};
        }

        throw new AutorouteException(
            "Operation not found: " . $uri . "(" . $method . ")"
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

    public function isSecured(string $uri, string $method): bool
    {
        throw new AutorouteException("TODO");
    }

    // TODO: Run authorization only when:
    // `$gate->policies[$modelName . "Policy"]` is set?
    // Or keep strong "Unauthorized" by default?

    public function authorize(string $action, string $uri, array $parameters)
    {
        $models = $this->resolver->getRouteModels($uri, $parameters);

        $name = $this->resolver->getAbilityName($uri, $action);
        $args = $this->getAbilityArgs($models, $uri, $parameters);

        return [$name, $args];
    }

    protected function getAbilityArgs(
        Collection $models,
        string $uri,
        array $parameters
    ) {
        $args = $models->all(); // Array

        if (count($parameters) < count($models)) {
            // Ex: `/users/123/comments`
            // $this->authorize('list', App\Models\Comment::class, $user);

            array_unshift(
                $args,
                $this->resolver->getRouteModelName($uri, $parameters)
            );
        }

        return $args;
    }

    public function queryByRoute(string $action, string $uri, array $parameters)
    {
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
        string $uri,
        array $parameters,
        array $data
    ) {
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
