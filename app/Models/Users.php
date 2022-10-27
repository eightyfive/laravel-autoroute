<?php
namespace App\Models;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;

class Users extends Models
{
    public function __construct(User $user)
    {
        parent::__construct($user);
    }

    public function listByRoute()
    {
        return $this->getAll("name", "asc");
    }

    public function createByRoute(Route $route, Request $request)
    {
        return $this->model->create($request->all());
    }

    public function readByRoute(Route $route)
    {
        return $route->parameter("user_id");
    }

    public function updateByRoute(Route $route, Request $request)
    {
        $user = $route->parameter("user_id");

        $user->fill($request->all());
        $user->save();

        return $user;
    }
}
