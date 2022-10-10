<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;

// use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    use HandlesAuthorization;

    public function createUser(User $authenticated, User $user)
    {
        return $user->is($authenticated);
    }
}
