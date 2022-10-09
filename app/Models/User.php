<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as BaseUser;

use Database\Factories\UserFactory;

class User extends BaseUser
{
    use HasFactory;

    protected $table = "users";

    protected static function newFactory()
    {
        return UserFactory::new();
    }
}
