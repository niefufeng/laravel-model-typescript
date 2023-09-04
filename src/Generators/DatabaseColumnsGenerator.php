<?php

namespace NieFufeng\LaravelModelTypescript\Generators;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Types;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use NieFufeng\LaravelModelTypescript\Enums\TypeScriptTypes;
use NieFufeng\LaravelModelTypescript\Transit;
use NieFufeng\LaravelModelTypescript\TypeScriptDefinition;
use NieFufeng\LaravelModelTypescript\Utils\Helper;

class DatabaseColumnsGenerator implements GeneratorContract
{
    /**
     * @throws Exception
     * @throws \ReflectionException
     */
    public function generate(Transit $transit, \Closure $next)
    {
        $columns = $this->getTableColumns($transit->model);

        $transit->databaseColumns = $columns->map(fn(Column $column) => $column->getName())->toArray();

        $columns->each(fn(Column $column) => $transit->definition[] = new TypeScriptDefinition(
            name: $column->getName(),
            types: $this->getTypes($column, $transit->model),
            readonly: $column->getName() === $transit->model->getKeyName(),
            nullable: !$column->getNotnull(),
            comment: $column->getComment(),
        ));

        return $next($transit);
    }

    /**
     * @throws \ReflectionException
     */
    public function getTypes(Column $column, Model $model): array|string|TypeScriptTypes
    {
        $type = match ($column->getType()->getName()) {
            Types::ASCII_STRING, Types::TEXT, Types::STRING, Types::GUID, Types::DATETIMETZ_IMMUTABLE, Types::DATETIMETZ_MUTABLE, Types::DATETIME_IMMUTABLE, Types::DATETIME_MUTABLE, Types::DATEINTERVAL, Types::DATE_IMMUTABLE, Types::DATE_MUTABLE, Types::BLOB, Types::BINARY => TypeScriptTypes::String,
            Types::BIGINT, Types::TIME_IMMUTABLE, Types::TIME_MUTABLE, Types::SMALLINT, Types::INTEGER, Types::FLOAT, Types::DECIMAL => TypeScriptTypes::Number,
            Types::BOOLEAN => TypeScriptTypes::Boolean,
            Types::JSON, Types::SIMPLE_ARRAY => [TypeScriptTypes::Array, TypeScriptTypes::Any],
            default => TypeScriptTypes::Any,
        };

        if ($model->hasCast($column->getName())) {
            return $this->getTypesByCast($model->getCasts()[$column->getName()], $type);
        }

        if ($model->hasGetMutator($column->getName())) {
            return $this->getTypesByGetMutator($model, $column->getName());
        }

        if ($model->hasAttributeGetMutator($column->getName())) {
            return $this->getTypesByAttributeGetMutator($model, $column->getName());
        }

        return $type;
    }

    /**
     * @throws \ReflectionException
     */
    protected function getTypesByGetMutator(Model $model, string $field): array|string|TypeScriptTypes
    {
        return Helper::convertReturnTypeToTypeScript(
            Helper::getReturnType([$model, 'get' . Str::studly($field) . 'Attribute'])
        );
    }

    /**
     * @throws \ReflectionException
     */
    protected function getTypesByCast(string $cast, mixed $defaultType): array|string|TypeScriptTypes
    {
        if ($cast === 'Illuminate\\Database\\Eloquent\\Casts\\AsStringable') {
            return [TypeScriptTypes::String, TypeScriptTypes::Null];
        }

        if (str_contains($cast, '\\')) {
            if (Helper::classInstanceOf($cast, Model::class)) {
                return Helper::classToDotClass($cast);
            }

            if (Helper::isEnum($cast)) {
                return Helper::enumValuesToTypescript(Helper::getEnumValues($cast));
            }

            return TypeScriptTypes::Any;
        }

        if (Str::startsWith($cast, 'decimal')) {
            return [TypeScriptTypes::Number];
        }

        if (Str::startsWith($cast, ['date', 'datetime'])) {
            return [TypeScriptTypes::String, TypeScriptTypes::Null];
        }

        return match ($cast) {
            'boolean', 'bool' => TypeScriptTypes::Boolean,
            'object', 'encrypted:object' => [TypeScriptTypes::Object, TypeScriptTypes::Null],
            'array', 'encrypted:array', 'collection', 'encrypted:collection' => [TypeScriptTypes::Array, TypeScriptTypes::Object, TypeScriptTypes::Null],
            'integer', 'int', 'float', 'double', 'decimal', 'real', 'timestamp' => [TypeScriptTypes::Number],
            default => $defaultType
        };
    }

    /**
     * @param Model $model
     * @return Collection<Column>
     * @throws Exception
     */
    protected function getTableColumns(Model $model): Collection
    {
        $connection = $model->getConnection();

        $connection->getDoctrineConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('tinyint', Types::SMALLINT);

        return collect($connection->getDoctrineSchemaManager()->listTableColumns(
            $connection->getTablePrefix() . $model->getTable()
        ));
    }

    /**
     * @throws \ReflectionException
     */
    protected function getTypesByAttributeGetMutator(Model $model, string $field): array|string|TypeScriptTypes
    {
        return Helper::convertReturnTypeToTypeScript(
            Helper::getReturnType(
                Helper::ensureFunctionReflected($model->{Str::camel($field)}()->get)
            )
        );
    }
}
