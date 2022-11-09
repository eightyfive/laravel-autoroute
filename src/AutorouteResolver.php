<?php
namespace Eyf\Autoroute;

use Symfony\Component\HttpFoundation\Response;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

use Eyf\Autoroute\Http\Controllers\VoidResponse;

class AutorouteResolver implements AutorouteResolverInterface
{
    public function getControllerString(
        string $operationId,
        string $uri,
        string $method
    ): string {
        if (strpos($operationId, "@") === false) {
            return $this->getDefaultControllerString($uri, $method);
        }

        return $operationId;
    }

    protected function getDefaultControllerString(
        string $uri,
        string $verb
    ): string {
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

    public function callOperation(
        string $operationId,
        Route $route,
        Request $request,
        $service = null
    ) {
        $callableOperation = $this->getCallableOperation(
            $operationId,
            $service
        );

        return call_user_func_array($callableOperation, [$route, $request]);
    }

    protected function getCallableOperation(
        string $operationId,
        $service = null
    ): callable {
        if (strpos($operationId, "::") === false) {
            if (!$service) {
                throw new AutorouteException(
                    "Service not found: ???::" . $operationId
                );
            }

            $instance = $service;
            $classMethod = $operationId;
        } else {
            list($classBaseName, $classMethod) = explode("::", $operationId);

            $className = $this->getModelsNamespace() . "\\" . $classBaseName;

            $instance = app()->make($className);
        }

        return [$instance, $classMethod];
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

        return (new SchemaResource($model))
            ->setSchema($schema)
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

        return (new SchemaResourceCollection($models))
            ->setSchema($schema)
            ->response()
            ->setStatusCode($status);
    }

    //
    // HELPERS
    //

    protected function getModelsNamespace()
    {
        // TODO: Pull from config('autoroute')
        return "App\\Models";
    }
}
