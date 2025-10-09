<?php

namespace App\Services;

abstract class BaseService
{
    protected $model;

    public function __construct($model = null)
    {
        $this->model = $model;
    }

    public function all()
    {
        return $this->model::all();
    }

    public function find($id)
    {
        return $this->model::findOrFail($id);
    }

    public function create(array $data)
    {
        return $this->model::create($data);
    }

    public function update($id, array $data)
    {
        $model = $this->find($id);
        $model->update($data);
        return $model;
    }

    public function delete($id)
    {
        $model = $this->find($id);
        return $model->delete();
    }

    public function paginate($perPage = 15)
    {
        return $this->model::paginate($perPage);
    }
}
