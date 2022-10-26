<?php
namespace App\Models;

class Users extends Models
{
    public function __construct(User $user)
    {
        parent::__construct($user);
    }

    public function list(array $data)
    {
        return $this->getAll("name", "asc");
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function read(User $user, array $data)
    {
        return $user;
    }

    public function update(User $user, array $data)
    {
        $user->fill($data);
        $user->save();

        return $user;
    }
}
