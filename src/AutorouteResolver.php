<?php
namespace Eyf\Autoroute;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class AutorouteResolver implements AutorouteResolverInterface
{
    protected $modelBaseNames;
    protected $modelNames;
    protected $parameterNames;

    //
    // Routing
    //

    public function getRouteModels(string $uri, array $parameters): Collection
    {
        $modelNames = $this->getModelNames($uri);

        $models = new Collection();
        $index = 0;

        foreach ($parameters as $parameter) {
            $model = $this->findModelByParameter(
                $modelNames[$index],
                $parameter
            );

            if ($model === null) {
                throw new NotFoundHttpException("Not Found");
            }

            $models->push($model);
            $index++;
        }

        return $models;
    }

    public function getRouteModel(string $uri, array $parameters): Model
    {
        $model = $this->getRouteModels($uri, $parameters)->last();

        if ($model instanceof Model) {
            return $model;
        }

        throw new AutorouteException(
            "Invalid model type: " . get_class($model)
        );
    }

    public function getRouteModelName(string $uri, array $parameters): string
    {
        $modelNames = $this->getModelNames($uri);

        return end($modelNames);
    }

    public function getOperationId(string $uri, string $method): string
    {
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
        $modelName = $this->getRouteModelName($uri, $parameters);
        $parameterName = $this->getParentParameterName($uri, $parameters);

        if ($parameterName) {
            $data[$parameterName] = $parameters[$parameterName];
        }

        return call_user_func([$modelName, "create"], $data);
    }

    public function readByRoute(string $uri, array $parameters): Model
    {
        return $this->getRouteModel($uri, $parameters);
    }

    public function updateByRoute(
        string $uri,
        array $parameters,
        array $data
    ): Model {
        $model = $this->getRouteModel($uri, $parameters);
        $model->fill($data);
        $model->save();

        return $model;
    }

    public function deleteByRoute(string $uri, array $parameters): void
    {
        $model = $this->getRouteModel($uri, $parameters);
        $model->delete();
    }

    public function listByRoute(string $uri, array $parameters): Collection
    {
        $modelName = $this->getRouteModelName($uri, $parameters);
        $parameterName = $this->getParentParameterName($uri);

        $query = call_user_func([$modelName, "query"]);

        if ($parameterName) {
            $query->where($parameterName, $parameters[$parameterName]);
        }

        return $query->get();
    }

    //
    // AUTHORIZATION
    //

    public function getAbilityName(string $uri, string $action): string
    {
        $modelBaseNames = $this->getModelBaseNames($uri);

        if (count($modelBaseNames) === 1) {
            return $action;
        }

        // Ex: /users/{user}/posts ("list")
        // ['User', 'Post'];

        array_pop($modelBaseNames); // remove 'Post'

        $parentBaseName = array_pop($modelBaseNames);

        return $action . $parentBaseName; // -> "listUser" (list user Posts !)
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
