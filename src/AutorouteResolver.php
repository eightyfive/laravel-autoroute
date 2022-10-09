<?php
namespace Eyf\Autoroute;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class AutorouteResolver implements AutorouteResolverInterface
{
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
        return $this->getRouteModels($uri, $parameters)->last();
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

        // TODO: Filter by relationship, by query params (?foo=bar)...

        // Ex: /users/123/comments
        // Comments of User `123` only...

        return call_user_func([$modelName, "all"]);
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

        // Ex: /users/{user}/posts
        // ['User', 'Post'];

        array_pop($modelBaseNames); // pop 'Post'

        return $action . array_pop($modelBaseNames); // -> `listUser` (Posts !)
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
        $modelBaseNames = $this->getModelBaseNames($uri);

        return array_map(function ($modelBaseName) {
            return $this->getModelsNamespace() . "\\" . $modelBaseName;
        }, $modelBaseNames);
    }

    protected function getModelBaseNames(string $uri)
    {
        $uri = ltrim($uri, "/");

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

    protected function getModelsNamespace()
    {
        // TODO
        return "App\\Models";
    }
}
