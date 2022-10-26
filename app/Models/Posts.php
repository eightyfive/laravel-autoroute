<?php
namespace App\Models;

class Posts extends Models
{
    public function __construct(User $user)
    {
        parent::__construct($user);
    }

    public function getByUser(User $user, array $data)
    {
        return $this->query()
            ->where("user_id", $user->id)
            ->get();
    }

    public function createByUser(User $user, array $data)
    {
        return $this->model->create(
            array_merge($data, [
                "user_id" => $user->id,
            ])
        );
    }

    public function findByUser(Post $post, User $user, array $data)
    {
        return $post;
    }

    public function updateByUser(Post $post, User $user, array $data)
    {
        $post->fill($data);
        $post->save();

        return $post;
    }

    public function deleteByUser(Post $post, User $user, array $data)
    {
        $post->delete();

        return $post;
    }
}
