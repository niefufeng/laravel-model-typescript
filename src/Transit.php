<?php

namespace NieFufeng\LaravelModelTypescript;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use NieFufeng\LaravelModelTypescript\Utils\Helper;
use ReflectionClass;

class Transit
{
    public readonly Model $model;

    public array $databaseColumns = [];

    public array $definition = [];

    public array $relationDefinitions = [];

    /**
     * @throws \ReflectionException
     */
    public function __construct(
        public readonly ReflectionClass $reflection,
        public readonly Queue $generatorQueue
    )
    {
        $this->model = $reflection->newInstance();
    }

    public function __toString(): string
    {
        return collect([
            '    export interface ' . class_basename($this->model) . (count($this->relationDefinitions) ? ' extends ' . class_basename($this->model) . 'Relations' : '') . ' {',
            collect($this->definition)->map(fn(TypeScriptDefinition $definition) => '        ' . $definition)->join(PHP_EOL),
            '    }',
        ])
            ->when(
                count($this->relationDefinitions) > 0,
                fn(Collection $collection) => $collection
                    ->push('    export interface ' . class_basename($this->model) . 'Relations' . ' {')
                    ->push(collect($this->relationDefinitions)->map(fn(TypeScriptDefinition $definition) => '        ' . $definition)->join(PHP_EOL))
                    ->push('    }')
            )
            ->join(PHP_EOL);
    }
}
