<?php
namespace Eyf\Autoroute;

use Symfony\Component\HttpFoundation\Response;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

use Eyf\Autoroute\Http\Controllers\VoidResponse;

class AutorouteResolver implements AutorouteResolverInterface
{
    protected $modelBaseNames;
    protected $modelNames;
    protected $parameterNames;

    public function getDefaultOperationId(string $uri, string $verb): string
    {
        $method = strtoupper($verb);

        $segments = explode("/", ltrim($uri, "/"));

        if ($method === Autoroute::METHOD_READ) {
            $action =
                count($segments) % 2 === 0
                    ? Autoroute::ACTION_READ
                    : Autoroute::ACTION_LIST;
        } elseif ($method === Autoroute::METHOD_CREATE) {
            $action = Autoroute::ACTION_CREATE;
        } elseif ($method === Autoroute::METHOD_UPDATE) {
            $action = Autoroute::ACTION_UPDATE;
        } elseif ($method === Autoroute::METHOD_DELETE) {
            $action = Autoroute::ACTION_DELETE;
        } else {
            throw new AutorouteException("Method not supported (PATCH)");
        }

        $uses = "\\Eyf\\Autoroute\\Http\Controllers\\";

        return $uses . "ResourceController@" . $action;
    }

    //
    // RESPONSE
    //

    public function toModelResponse(
        int $status,
        array|null $schema,
        Model $model
    ): Response {
        if (!$schema) {
            // No Content (~204)
            return new VoidResponse($status);
        }

        return (new SchemaResource($model, $schema))
            ->response()
            ->setStatusCode($status);
    }

    public function toModelsResponse(
        int $status,
        array|null $schema,
        Collection $models
    ): Response {
        if (!$schema) {
            // No Content (~204)
            return new VoidResponse($status);
        }

        return (new SchemaResourceCollection($models, $schema))
            ->response()
            ->setStatusCode($status);
    }

    //
    // HELPERS
    //

    protected function getModelNames(string $uri)
    {
        if (!isset($this->modelNames)) {
            $modelBaseNames = $this->getModelBaseNames($uri);

            $this->modelNames = array_map(function ($modelBaseName) {
                return $this->getModelsNamespace() . "\\" . $modelBaseName;
            }, $modelBaseNames);
        }

        return $this->modelNames;
    }

    protected function getModelBaseNames(string $uri)
    {
        if (!isset($this->modelBaseNames)) {
            $uri = ltrim($uri, "/");

            $segments = explode("/", $uri);

            // Filter non-parameter segments
            $segments = array_filter($segments, function ($segment) {
                $isParam = strpos($segment, "{") === 0;

                return !$isParam;
            });

            $modelBaseNames = array_map(function ($segment) {
                return $this->getModelBaseName($segment);
            }, $segments);

            $this->modelBaseNames = array_values($modelBaseNames);
        }

        return $this->modelBaseNames;
    }

    protected function getModelBaseName(string $segment)
    {
        return Str::ucfirst(Str::singular($segment));
    }

    protected function getModelsNamespace()
    {
        // TODO: Pull from config('autoroute')
        return "App\\Models";
    }

    protected function getParameterNames(string $uri)
    {
        if (!isset($this->parameterNames)) {
            $uri = ltrim($uri, "/");

            $segments = explode("/", $uri);

            // Filter parameter segments
            $segments = array_filter($segments, function ($segment) {
                $isParam = strpos($segment, "{") === 0;

                return $isParam;
            });

            $names = array_map(function ($segment) {
                $name = str_replace("{", "", $segment);
                $name = str_replace("}", "", $name);

                return $name;
            }, $segments);

            $this->parameterNames = array_values($names);
        }

        return $this->parameterNames;
    }

    protected function getParentParameterName(string $uri): string|null
    {
        $modelBaseNames = $this->getModelBaseNames($uri);
        $parameterNames = $this->getParameterNames($uri);

        if (
            count($parameterNames) &&
            count($parameterNames) < count($modelBaseNames)
        ) {
            return end($parameterNames);
        }

        return null;
    }
}
