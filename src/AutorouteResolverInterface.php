<?php
namespace Eyf\Autoroute;

use Symfony\Component\HttpFoundation\Response;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface AutorouteResolverInterface
{
    public function getDefaultOperationId(string $uri, string $method): string;

    public function getOperationIdCallable(string $operationId): callable;

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
