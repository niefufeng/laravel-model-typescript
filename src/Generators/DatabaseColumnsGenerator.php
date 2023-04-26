<?php

namespace NieFufeng\LaravelModelTypescript\Generators;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Types;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use NieFufeng\LaravelModelTypescript\Enums\TypeScriptTypes;
use NieFufeng\LaravelModelTypescript\Transit;
use NieFufeng\LaravelModelTypescript\TypeScriptDefinition;
use NieFufeng\LaravelModelTypescript\Utils\ClassHelper;

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
            types: $this->convertDatabaseColumnToTypeScriptType(
                $column,
                $transit->model->getCasts()[$column->getName()] ?? null
            ),
            readonly: $column->getName() === $transit->model->getKeyName(),
            nullable: !$column->getNotnull(),
            comment: $column->getComment(),
        ));

        return $next($transit);
    }

    /**
     * @throws \ReflectionException
     */
    public function convertDatabaseColumnToTypeScriptType(Column $column, string $cast = null): array|string|TypeScriptTypes
    {
        $type = match ($column->getType()->getName()) {
            Types::ASCII_STRING, Types::TEXT, Types::STRING, Types::GUID, Types::DATETIMETZ_IMMUTABLE, Types::DATETIMETZ_MUTABLE, Types::DATETIME_IMMUTABLE, Types::DATETIME_MUTABLE, Types::DATEINTERVAL, Types::DATE_IMMUTABLE, Types::DATE_MUTABLE, Types::BLOB, Types::BINARY => TypeScriptTypes::String,
            Types::BIGINT, Types::TIME_IMMUTABLE, Types::TIME_MUTABLE, Types::SMALLINT, Types::INTEGER, Types::FLOAT, Types::DECIMAL => TypeScriptTypes::Number,
            Types::BOOLEAN => TypeScriptTypes::Boolean,
            Types::JSON, Types::SIMPLE_ARRAY => [TypeScriptTypes::Array, TypeScriptTypes::Any],
            default => TypeScriptTypes::Any,
        };

        if ($cast === null) {
            return $type;
        }

        if ($cast === 'Illuminate\\Database\\Eloquent\\Casts\\AsStringable') {
            return [TypeScriptTypes::String, TypeScriptTypes::Null];
        }

        if (str_contains($cast, '\\')) {
            if (ClassHelper::classInstanceOf($cast, Model::class)) {
                return ClassHelper::namespaceClassToDotClass($cast);
            }

            if (ClassHelper::isEnum($cast)) {
                return ClassHelper::getEnumValues($cast);
            }

            return TypeScriptTypes::Any;
        }

        return match ($cast) {
            'boolean', 'bool' => TypeScriptTypes::Boolean,
            'object' => [TypeScriptTypes::Object, TypeScriptTypes::Null],
            'array' => [TypeScriptTypes::Array, TypeScriptTypes::Object, TypeScriptTypes::Null],
            default => $type
        };
    }

    /**
     * @param Model $model
     * @return Collection<Column>
     * @throws Exception
     */
    protected function getTableColumns(Model $model): Collection
    {
        $model->getConnection()->getDoctrineConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('tinyint', Types::SMALLINT);

        return collect($model->getConnection()->getDoctrineSchemaManager()->listTableColumns(
            $model->getConnection()->getTablePrefix() . $model->getTable()
        ));
    }
}
