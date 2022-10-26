<?php
namespace Eyf\Autoroute;

use Symfony\Component\HttpFoundation\Response;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface AutorouteResolverInterface
{
    public function createByRoute(
        string $uri,
        array $parameters,
        array $data
    ): Model;

    public function readByRoute(string $uri, array $parameters): Model;

    public function updateByRoute(
        string $uri,
        array $parameters,
        array $data
    ): Model;

    public function deleteByRoute(string $uri, array $parameters): Model;

    public function listByRoute(string $uri, array $parameters): Collection;

    public function getResourceModelName(string $uri): string|null;

    public function getDefaultOperationId(string $uri, string $method): string;

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
