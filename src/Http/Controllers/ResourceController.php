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

        // Authorize
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

        // Validate
        $request->validate(
            $this->autoroute->getRequest($route->uri, $request->method())
        );

        // Mutate
        $model = $this->autoroute->mutateByRoute(
            Autoroute::ACTION_CREATE,
            $route->uri,
            $parameters,
            $request->all()
        );

        // TODO: Filter response by OpenAPI 3.0 definition response schema
        return response()->json($model, 201);
    }

    public function read(Request $request)
    {
        $route = $request->route();
        $parameters = $route->parameters();

        // Authorize
        $secured = $this->autoroute->isSecured(
            $route->uri,
            Autoroute::METHOD_READ
        );

        if ($secured) {
            [$ability, $args] = $this->autoroute->authorize(
                Autoroute::ACTION_READ,
                $route->uri,
                $parameters
            );

            $this->authorize($ability, $args);
        }

        // Query
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

        // Authorize
        $secured = $this->autoroute->isSecured(
            $route->uri,
            Autoroute::METHOD_UPDATE
        );

        if ($secured) {
            [$ability, $args] = $this->autoroute->authorize(
                Autoroute::ACTION_UPDATE,
                $route->uri,
                $parameters
            );

            $this->authorize($ability, $args);
        }

        // Validate
        $request->validate(
            $this->autoroute->getRequest($route->uri, $request->method())
        );

        // Mutate
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

    public function delete(Request $request)
    {
        $route = $request->route();
        $parameters = $route->parameters();

        // Authorize
        $secured = $this->autoroute->isSecured(
            $route->uri,
            Autoroute::METHOD_DELETE
        );

        if ($secured) {
            [$ability, $args] = $this->autoroute->authorize(
                Autoroute::ACTION_DELETE,
                $route->uri,
                $parameters
            );

            $this->authorize($ability, $args);
        }

        // Mutate
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

        // Authorize
        $secured = $this->autoroute->isSecured(
            $route->uri,
            Autoroute::METHOD_LIST
        );

        if ($secured) {
            [$ability, $args] = $this->autoroute->authorize(
                Autoroute::ACTION_LIST,
                $route->uri,
                $parameters
            );

            $this->authorize($ability, $args);
        }

        // Query
        $models = $this->autoroute->queryByRoute(
            Autoroute::ACTION_LIST,
            $route->uri,
            $parameters
        );

        // TODO
        return response()->json($models);
    }
}
