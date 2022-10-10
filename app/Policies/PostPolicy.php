<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    use HandlesAuthorization;

    public function createUser(User $authenticated, User $user)
    {
        return $user->is($authenticated);
    }

    public function readUser(User $authenticated, Post $post, User $user)
    {
        return $user->is($authenticated) && $post->user_id == $user->id;
    }
}
