<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class Models
{
    protected $model;
    protected $idAttribute;
    protected $slugAttribute = "slug";

    public function __construct(Model $model)
    {
        $this->model = $model;
        $this->idAttribute = $model->getKeyName();
    }

    protected function query()
    {
        return $this->model->newQuery();
    }

    protected function queryAll()
    {
        return $this->query();
    }

    public function save(Model $model)
    {
        $model->save();
    }

    public function findById($id)
    {
        return $this->query()
            ->where($this->idAttribute, $id)
            ->first();
    }

    public function find($id)
    {
        return $this->findById($id);
    }

    public function findBySlug($slug)
    {
        $attr = is_numeric($slug) ? $this->idAttribute : $this->slugAttribute;

        return $this->query()
            ->where($attr, $slug)
            ->first();
    }

    public function getAll($orderBy = null, $orderDir = "asc")
    {
        $query = $this->queryAll();

        if ($orderBy) {
            $query->orderBy($orderBy, $orderDir);
        }

        return $query->get();
    }

    public function getByIds(array $ids)
    {
        return $this->query()
            ->whereIn("id", $ids)
            ->get();
    }
}
