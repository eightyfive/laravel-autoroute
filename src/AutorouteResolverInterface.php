<?php
namespace Eyf\Autoroute;

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

    public function deleteByRoute(string $uri, array $parameters): void;

    public function listByRoute(string $uri, array $parameters): Collection;

    public function getRouteModels(string $uri, array $parameters): Collection;

    public function getRouteModelName(string $uri, array $parameters): string;

    public function getOperationId(string $uri, string $method): string;

    public function getAbilityName(string $uri, string $action): string;
}
