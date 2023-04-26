<?php

namespace NieFufeng\LaravelModelTypescript\Generators;

use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use NieFufeng\LaravelModelTypescript\Enums\TypeScriptTypes;
use NieFufeng\LaravelModelTypescript\Transit;
use NieFufeng\LaravelModelTypescript\TypeScriptDefinition;
use NieFufeng\LaravelModelTypescript\Utils\ClassHelper;
use ReflectionMethod;

class AccessorsGenerator implements GeneratorContract
{
    /**
     * @param Transit $transit
     * @param \Closure $next
     * @return mixed
     * @throws \ReflectionException
     */
    public function generate(Transit $transit, \Closure $next)
    {
        $this->getAccessors($transit)
            ->each(function (ReflectionMethod $method) use ($transit) {
                if (str_starts_with($method->name, 'get') && str_ends_with($method->name, 'Attribute')) {
                    $fieldName = Str::snake(Str::of($method->name)->between('get', 'Attribute')->toString());

                    if (ClassHelper::hasReturnType($method)) {
                        $types = $this->convertPhpReturnTypeToTypeScriptType(ClassHelper::getReturnType($method));
                    } else {
                        $types = TypeScriptTypes::Any;
                    }

                    $transit->definition[] = new TypeScriptDefinition(
                        name: $fieldName,
                        types: $types,
                        optional: !in_array($fieldName, $transit->databaseColumns),
                        readonly: !in_array($fieldName, $transit->databaseColumns),
                        nullable: in_array(TypeScriptTypes::Null, (array)$types)
                    );

                    return;
                }

                $fieldName = Str::snake($method->name);

                /** @var Attribute $attribute */
                $attribute = $method->invoke($transit->model);

                $getReflection = new \ReflectionFunction($attribute->get);

                if (ClassHelper::hasReturnType($getReflection)) {
                    $types = $this->convertPhpReturnTypeToTypeScriptType(
                        ClassHelper::getReturnType($getReflection)
                    );
                } else {
                    $types = TypeScriptTypes::Any;
                }

                $transit->definition[] = new TypeScriptDefinition(
                    name: $fieldName,
                    types: $types,
                    optional: !in_array($fieldName, $transit->databaseColumns),
                    readonly: !in_array($fieldName, $transit->databaseColumns),
                    nullable: in_array(TypeScriptTypes::Null, (array)$types)
                );
            });

        return $next($transit);
    }

    /**
     * @param Transit $transit
     * @return Collection<ReflectionMethod>
     */
    protected function getAccessors(Transit $transit): Collection
    {
        return collect($transit->reflection->getMethods(ReflectionMethod::IS_PUBLIC))
            ->reject(function (ReflectionMethod $method) use ($transit) {
                if ($method->isStatic() || $method->isAbstract()) {
                    return true;
                }

                // getFieldAttribute
                if (str_starts_with($method->name, 'get') && str_ends_with($method->name, 'Attribute') && strlen($method->name) > 12) {
                    return false;
                }

                $returnType = ClassHelper::getReturnType($method);

                if ($returnType === null) {
                    return true;
                }

                // function avatarUrl() :Attribute
                if ((string)$returnType !== 'Illuminate\Database\Eloquent\Casts\Attribute') {
                    return true;
                }

                /** @var Attribute $attribute */
                $attribute = $method->invoke($transit->model);

//                if ($attribute->get === null) {
//                    return true;
//                }
//
//                return !ClassHelper::hasReturnType(new \ReflectionFunction($attribute->get));

                return $attribute->get === null;
            });
    }

    protected function convertPhpReturnTypeToTypeScriptType(\ReflectionType|string|null $type): array|string|TypeScriptTypes
    {
        if ($type === null) {
            return TypeScriptTypes::Null;
        }

        if (is_string($type)) {
            return $this->convertPhpBaseTypeToTypeScriptType($type);
        }

        $returnTypes = [];

        foreach ($type instanceof \ReflectionNamedType ? [$type] : $type->types as $type) {
            /** @var \ReflectionType $type */
            if ($type->allowsNull()) {
                $returnTypes[] = TypeScriptTypes::Null;
            }

            if ($type instanceof \ReflectionNamedType) {
                $returnTypes[] = $this->convertPhpBaseTypeToTypeScriptType($type->getName());

                continue;
            }

            $returnTypes[] = $this->convertPhpReturnTypeToTypeScriptType($type);
        }

        $returnTypes = collect($returnTypes)
            ->flatten(1)
            ->map(function (array|string|TypeScriptTypes $type) {
                if (is_array($type) && count($type) === 1) {
                    $type = $type[0];
                }

                if ($type instanceof TypeScriptTypes) {
                    return $type->value;
                }

                return $type;
            })
            ->unique();

        return $returnTypes->count() === 1 ? $returnTypes->first() : $returnTypes->toArray();
    }

    protected function convertPhpBaseTypeToTypeScriptType(string $type): array|string|TypeScriptTypes
    {
        if (str_contains($type, '\\')) {
            if (ClassHelper::classInstanceOf($type, Model::class)) {
                return ClassHelper::namespaceClassToDotClass($type);
            }
            if (ClassHelper::classIsEnum($type)) {
                return collect((new \ReflectionClass($type))->getConstants())->map(fn($constant) => '"' . $constant->value . '"')->toArray();
            }

            return TypeScriptTypes::Any;
        }

        return match ($type) {
            'string' => TypeScriptTypes::String,
            'int', 'float' => TypeScriptTypes::Number,
            'array' => [TypeScriptTypes::Array, TypeScriptTypes::Object],
            'bool' => TypeScriptTypes::Boolean,
            default => TypeScriptTypes::Any
        };
    }
}
