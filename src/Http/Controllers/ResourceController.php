<?php

namespace Eyf\Autoroute\Http\Controllers;

use Illuminate\Http\Request;

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

        // Validate
        $request->validate($this->autoroute->getValidationRules($request));

        // Mutate
        $model = $this->autoroute->mutateByRoute(
            Autoroute::ACTION_CREATE,
            $route->uri,
            $parameters,
            $request->all()
        );

        // Response
        return $this->autoroute->toModelResponse(
            $route->uri,
            Autoroute::ACTION_CREATE,
            $model
        );
    }

    public function read(Request $request)
    {
        $route = $request->route();
        $parameters = $route->parameters();

        // Query
        $model = $this->autoroute->queryByRoute(
            Autoroute::ACTION_READ,
            $route->uri,
            $parameters
        );

        // Response
        return $this->autoroute->toModelResponse(
            $route->uri,
            Autoroute::ACTION_READ,
            $model
        );
    }

    public function update(Request $request)
    {
        $route = $request->route();
        $parameters = $route->parameters();

        // Validate
        $request->validate($this->autoroute->getValidationRules($request));

        // Mutate
        $model = $this->autoroute->mutateByRoute(
            Autoroute::ACTION_UPDATE,
            $route->uri,
            $parameters,
            $request->all()
        );

        // Response
        return $this->autoroute->toModelResponse(
            $route->uri,
            Autoroute::ACTION_UPDATE,
            $model
        );
    }

    public function delete(Request $request)
    {
        $route = $request->route();
        $parameters = $route->parameters();

        // Mutate
        $model = $this->autoroute->mutateByRoute(
            Autoroute::ACTION_DELETE,
            $route->uri,
            $parameters,
            []
        );

        // Response
        return $this->autoroute->toModelResponse(
            $route->uri,
            Autoroute::ACTION_DELETE,
            $model
        );
    }

    public function list(Request $request)
    {
        $route = $request->route();
        $parameters = $route->parameters();

        // Query
        $models = $this->autoroute->queryByRoute(
            Autoroute::ACTION_LIST,
            $route->uri,
            $parameters
        );

        // Response
        return $this->autoroute->toModelsResponse(
            $route->uri,
            Autoroute::ACTION_LIST,
            $models
        );
    }
}
