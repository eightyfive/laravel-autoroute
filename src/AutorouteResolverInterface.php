<?php
namespace Eyf\Autoroute;

use Symfony\Component\HttpFoundation\Response;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Route;

interface AutorouteResolverInterface
{
    public function getControllerString(
        string $operationId,
        string $uri,
        string $method
    ): string;

    public function callOperation(
        string $operationId,
        Route $route,
        Request $request,
        $service = null
    );

    public function toModelResource(Model $model, array $schema): JsonResource;

    public function toModelResponse(
        int $status,
        array|null $schema,
        Model $model
    ): Response;

    public function toModelsResponse(
        int $status,
        array|null $schema,
        Collection $models
    ): Response;
}
