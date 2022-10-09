<?php

namespace Eyf\Autoroute\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;

use Eyf\Autoroute\Autoroute;

class ResourceController extends Controller
{
    protected $autoroute;

    public function __construct(Autoroute $autoroute)
    {
        $this->autoroute = $autoroute;
    }

    public function create(Request $request)
    {
        $route = $request->route();
        $parameters = $route->parameters();

        $secured = $this->autoroute->isSecured(
            $route->uri,
            Autoroute::METHOD_CREATE
        );

        if ($secured) {
            [$ability, $args] = $this->autoroute->authorize(
                Autoroute::ACTION_CREATE,
                $route->uri,
                $parameters
            );

            $this->authorize($ability, $args);
        }

        $model = $this->autoroute->mutateByRoute(
            Autoroute::ACTION_CREATE,
            $route->uri,
            $parameters,
            $request->all()
        );

        // TODO: Filter response by OpenAPI 3.0 definition response schema
        return response()->json($model);
    }

    public function read(Request $request)
    {
        $route = $request->route();
        $parameters = $route->parameters();

        $secured = $this->autoroute->isSecured(
            $route->uri,
            Autoroute::METHOD_READ
        );

        if ($secured) {
            [$ability, $arguments] = $this->autoroute->authorize(
                Autoroute::ACTION_READ,
                $route->uri,
                $parameters
            );

            $this->authorize($ability, $arguments);
        }

        $model = $this->autoroute->queryByRoute(
            Autoroute::ACTION_READ,
            $route->uri,
            $parameters
        );

        // TODO
        return response()->json($model);
    }

    public function update(Request $request)
    {
        $route = $request->route();
        $parameters = $route->parameters();

        $secured = $this->autoroute->isSecured(
            $route->uri,
            Autoroute::METHOD_UPDATE
        );

        if ($secured) {
            [$ability, $arguments] = $this->autoroute->authorize(
                Autoroute::ACTION_UPDATE,
                $route->uri,
                $parameters
            );

            $this->authorize($ability, $arguments);
        }

        // TODO: Validation
        // $rules = $this->autoroute->getRequest( ... )
        // $request->validate($rules);

        $model = $this->autoroute->mutateByRoute(
            Autoroute::ACTION_UPDATE,
            $route->uri,
            $parameters,
            $request->all()
        );

        // TODO
        return response()->json($model);
        // TODO: return new VoidResponse();
    }

    public function delete(Request $request, Model $model)
    {
        $route = $request->route();
        $parameters = $route->parameters();

        $secured = $this->autoroute->isSecured(
            $route->uri,
            Autoroute::METHOD_DELETE
        );

        if ($secured) {
            [$ability, $arguments] = $this->autoroute->authorize(
                Autoroute::ACTION_DELETE,
                $route->uri,
                $parameters
            );

            $this->authorize($ability, $arguments);
        }

        $this->autoroute->mutateByRoute(
            Autoroute::ACTION_DELETE,
            $route->uri,
            $parameters,
            []
        );

        // TODO: Change according to OpenAPi 3.0 definition
        return new VoidResponse();
    }

    public function list(Request $request)
    {
        $route = $request->route();
        $parameters = $route->parameters();

        $secured = $this->autoroute->isSecured(
            $route->uri,
            Autoroute::METHOD_LIST
        );

        if ($secured) {
            [$ability, $arguments] = $this->autoroute->authorize(
                Autoroute::ACTION_LIST,
                $route->uri,
                $parameters
            );

            $this->authorize($ability, $arguments);
        }

        $models = $this->autoroute->queryByRoute(
            Autoroute::ACTION_LIST,
            $route->uri,
            $parameters
        );

        // TODO
        return response()->json($models);
    }
}
