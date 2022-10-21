<?php
namespace Eyf\Autoroute;

use Symfony\Component\HttpFoundation\Response;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

use Eyf\Autoroute\Http\Controllers\VoidResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class AutorouteResolver implements AutorouteResolverInterface
{
    protected $modelBaseNames;
    protected $modelNames;
    protected $parameterNames;

    //
    // Routing
    //

    protected function getResourceModel(array $parameters): Model
    {
        $model = end($parameters);

        if ($model instanceof Model) {
            return $model;
        }

        throw new AutorouteException(
            "Invalid model type: " . get_class($model)
        );
    }

    public function getResourceModelName(string $uri): string|null
    {
        $modelNames = $this->getModelNames($uri);
        $parameterNames = $this->getParameterNames($uri);

        $isAnonymous = count($modelNames) > count($parameterNames);

        if ($isAnonymous) {
            return end($modelNames);
        }

        return null;
    }

    public function getOperationId(string $uri, string $verb): string
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
    // Eloquent
    //

    public function createByRoute(
        string $uri,
        array $parameters,
        array $data
    ): Model {
        $modelName = $this->getResourceModelName($uri);

        if (!$modelName) {
            throw new AutorouteException("Invalid create uri: " . $uri);
        }

        $parameterName = $this->getParentParameterName($uri, $parameters);

        if ($parameterName) {
            $data[$parameterName] = $parameters[$parameterName];
        }

        return call_user_func([$modelName, "create"], $data);
    }

    public function readByRoute(string $uri, array $parameters): Model
    {
        return $this->getResourceModel($parameters);
    }

    public function updateByRoute(
        string $uri,
        array $parameters,
        array $data
    ): Model {
        $model = $this->getResourceModel($parameters);
        $model->fill($data);
        $model->save();

        return $model;
    }

    public function deleteByRoute(string $uri, array $parameters): Model
    {
        $model = $this->getResourceModel($parameters);
        $model->delete();

        return $model;
    }

    public function listByRoute(string $uri, array $parameters): Collection
    {
        $modelName = $this->getResourceModelName($uri);

        if (!$modelName) {
            throw new AutorouteException("Invalid list uri: " . $uri);
        }

        $query = call_user_func([$modelName, "query"]);

        $parameterName = $this->getParentParameterName($uri);

        if ($parameterName) {
            $model = $parameters[$parameterName];

            $query->where($parameterName, $model->id);
        }

        return $query->get();
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

        $model->setVisible(array_keys($schema));

        return (new JsonResource($model))->response()->setStatusCode($status);
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

        $models->each->setVisible(array_keys($schema));

        return JsonResource::collection($models)
            ->response()
            ->setStatusCode($status);
    }

    //
    // HELPERS
    //

    protected function findModelByParameter(string $modelName, string $id)
    {
        return call_user_func([$modelName, "find"], $id);
    }

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
