<?php
namespace App\Models;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;

class Posts extends Models
{
    public function __construct(Post $post)
    {
        parent::__construct($post);
    }

    public function getByRoute(Route $route)
    {
        $user = $route->parameter("user_id");

        return $this->query()
            ->where("user_id", $user->id)
            ->get();
    }

    public function createByRoute(Route $route, Request $request)
    {
        $user = $route->parameter("user_id");

        return $this->model->create(
            array_merge($request->all(), [
                "user_id" => $user->id,
            ])
        );
    }

    public function findByRoute(Route $route)
    {
        return $route->parameter("post_id");
    }

    public function updateByRoute(Route $route, Request $request)
    {
        $post = $route->parameter("post_id");

        $post->fill($request->all());
        $post->save();

        return $post;
    }

    public function deleteByRoute(Route $route)
    {
        $post = $route->parameter("post_id");

        $post->delete();

        return $post;
    }
}
