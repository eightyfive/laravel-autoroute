<?php
namespace Eyf\Autoroute;

use Symfony\Component\HttpFoundation\Response;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

use Eyf\Autoroute\Http\Controllers\VoidResponse;

class AutorouteResolver implements AutorouteResolverInterface
{
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

    public function getCallableOperationId(string $operationId): callable
    {
        list($className, $classMethod) = explode("::", $operationId);

        return ["App\\Models\\{$className}", $classMethod];
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

    protected function getModelsNamespace()
    {
        // TODO: Pull from config('autoroute')
        return "App\\Models";
    }
}
