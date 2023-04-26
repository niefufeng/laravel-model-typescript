<?php

namespace NieFufeng\LaravelModelTypescript;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use ReflectionClass;

class Queue
{
    /**
     * @var Collection<int, ReflectionClass>
     */
    public Collection $models;

    public function __construct()
    {
        $this->models = collect();
    }

    /**
     * @param string|Model ...$models
     * @return void
     * @throws \ReflectionException
     */
    public function push(string|Model ...$models): void
    {
        $this->models->push(
            ...array_map(fn(string|Model $model) => new ReflectionClass($model), $models)
        );
    }

    public function pop(): ReflectionClass|null
    {
        return $this->models->pop();
    }
}
