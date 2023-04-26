<?php

namespace NieFufeng\LaravelModelTypescript\Generators;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use NieFufeng\LaravelModelTypescript\Enums\TypeScriptTypes;
use NieFufeng\LaravelModelTypescript\Transit;
use NieFufeng\LaravelModelTypescript\TypeScriptDefinition;
use NieFufeng\LaravelModelTypescript\Utils\ClassHelper;
use ReflectionMethod;

class RelationsGenerator implements GeneratorContract
{
    protected array $relationMethods = [
        'hasMany',
        'hasManyThrough',
        'hasOneThrough',
        'belongsToMany',
        'hasOne',
        'belongsTo',
        'morphOne',
        'morphTo',
        'morphMany',
        'morphToMany',
        'morphedByMany',
    ];

    public function generate(Transit $transit, \Closure $next)
    {
        $this->getRelations($transit)->each(function (ReflectionMethod $method) use ($transit) {
            /** @var Relation $relationReturn */
            $relationReturn = $method->invoke($transit->model);

            if ($this->isManyRelation($relationReturn::class)) {
                $transit->relationDefinitions[] = new TypeScriptDefinition(
                    name: Str::snake($method->name),
                    types: 'Array<' . ClassHelper::namespaceClassToDotClass(get_class($relationReturn->getRelated())) . '>',
                    optional: true,
                    readonly: true,
                    nullable: false
                );

                $transit->definition[] = new TypeScriptDefinition(
                    name: Str::snake($method->name) . '_count',
                    types: TypeScriptTypes::Number,
                    optional: true,
                    readonly: true,
                );

                $transit->generatorQueue->push(get_class($relationReturn->getRelated()));
            } else if ($this->isMorphToRelation($relationReturn::class)) {
                $transit->relationDefinitions[] = new TypeScriptDefinition(
                    name: Str::snake($method->name),
                    types: TypeScriptTypes::Any,
                    optional: true,
                    readonly: true,
                    nullable: true
                );
            } else if ($this->isOneRelation($relationReturn::class)) {
                $transit->relationDefinitions[] = new TypeScriptDefinition(
                    name: Str::snake($method->name),
                    types: ClassHelper::namespaceClassToDotClass(get_class($relationReturn->getRelated())),
                    optional: true,
                    readonly: true,
                    nullable: true
                );

                $transit->generatorQueue->push(get_class($relationReturn->getRelated()));
            } else {
                $transit->relationDefinitions[] = new TypeScriptDefinition(
                    name: Str::snake($method->name),
                    types: TypeScriptTypes::Any,
                    optional: true,
                    readonly: true
                );
            }
        });

        return $next($transit);
    }

    protected function isManyRelation(string $relation): bool
    {
        return in_array(
            $relation,
            [
                BelongsToMany::class,
                HasMany::class,
                HasManyThrough::class,
                MorphMany::class,
                MorphToMany::class,
            ]
        );
    }

    protected function isMorphToRelation(string $relation): bool
    {
        return $relation === MorphTo::class;
    }

    protected function isOneRelation(string $relation): bool
    {
        return in_array(
            $relation,
            [
                BelongsTo::class,
                HasOne::class,
                HasOneThrough::class,
                MorphOne::class,
                MorphTo::class,
            ]
        );
    }

    /**
     * @param Transit $transit
     * @return Collection
     */
    protected function getRelations(Transit $transit): Collection
    {
        return collect($transit->reflection->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(fn(ReflectionMethod $method) => $this->isRelationMethod($method, $transit));
    }

    protected function isRelationMethod(ReflectionMethod $method, Transit $transit): bool
    {
//        if ($method->isStatic() || $method->isAbstract() || $method->getDeclaringClass()->getName() !== get_class($transit->model)) {
//            return false;
//        }

        if ($method->isStatic() || $method->isAbstract()) {
            return false;
        }

        if ($method->getNumberOfParameters() > 0) {
            return false;
        }

        $file = new \SplFileObject($method->getFileName());
        $file->seek($method->getStartLine() - 1);
        $code = '';
        while ($file->key() < $method->getEndLine()) {
            $code .= trim($file->current());
            $file->next();
        }

        if (!collect($this->relationMethods)->contains(fn(string $relationMethod) => str_contains($code, '$this->' . $relationMethod . '('))) {
            return false;
        }

        try {
            return $method->invoke($transit->model) instanceof Relation;
        } catch (\ReflectionException) {
            return false;
        }
    }
}
