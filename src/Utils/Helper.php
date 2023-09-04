<?php

namespace NieFufeng\LaravelModelTypescript\Utils;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use NieFufeng\LaravelModelTypescript\Enums\TypeScriptTypes;
use NieFufeng\LaravelModelTypescript\Exceptions\GenerateException;

class Helper
{
    /**
     * 类是否是另一个类的实例
     * @param string|\ReflectionClass $class
     * @param string $instanceOf
     * @return bool
     */
    public static function classInstanceOf(string|\ReflectionClass $class, string $instanceOf): bool
    {
        try {
            return static::ensureClassReflected($class)->isSubclassOf($instanceOf);
        } catch (\ReflectionException) {
            return false;
        }
    }

    /**
     * 类是否是一个枚举
     * @param string|\ReflectionClass $class
     * @return bool
     */
    public static function classIsEnum(string|\ReflectionClass $class): bool
    {
        try {
            return static::ensureClassReflected($class)->isEnum();
        } catch (\ReflectionException) {
            return false;
        }
    }

    /**
     * 将类名转换为类似 App.Models.User 的格式
     * @param string $class
     * @return string
     */
    public static function classToDotClass(string $class): string
    {
        return str_replace('\\', '.', $class);
    }

    /**
     * @throws \ReflectionException
     */
    public static function ensureClassReflected(string|\ReflectionClass $class): \ReflectionClass
    {
        return is_string($class) ? new \ReflectionClass($class) : $class;
    }

    /**
     * @throws \ReflectionException
     */
    public static function ensureEnumReflected(string|\ReflectionEnum $enum): \ReflectionEnum
    {
        return is_string($enum) ? new \ReflectionEnum($enum) : $enum;
    }

    /**
     * @throws \ReflectionException
     */
    public static function ensureMethodReflected(\ReflectionMethod|callable $method): \ReflectionMethod
    {
        return $method instanceof \ReflectionMethod ? $method : new \ReflectionMethod($method);
    }

    /**
     * @throws \ReflectionException
     */
    public static function ensureFunctionReflected(\ReflectionFunction|string $function): \ReflectionFunction
    {
        return $function instanceof \ReflectionFunction ? $function : new \ReflectionFunction($function);
    }

    /**
     * 获取方法或者函数的文本注释的返回类型
     * @param \ReflectionMethod|\ReflectionFunction|callable $reflection
     * @return string|null
     * @throws \ReflectionException
     */
    public static function getDocReturnType(\ReflectionMethod|\ReflectionFunction|callable $reflection): ?string
    {
        $reflection = self::ensureMethodOrFunctionReflected($reflection);

        $docComment = $reflection->getDocComment();

        if ($docComment === false) {
            return null;
        }

        $pattern = '/@return\s+(.*?)[\s\n]/';

        if (preg_match($pattern, $docComment, $matched) !== 1) {
            return null;
        }

        return trim($matched[1]) === '' ? null : trim($matched[1]);
    }

    /**
     * @throws \ReflectionException
     */
    public static function getReturnType(\ReflectionMethod|\ReflectionFunction|callable $reflection): null|string|\ReflectionType
    {
        $reflection = static::ensureMethodOrFunctionReflected($reflection);

        if ($reflection->hasReturnType()) {
            return $reflection->getReturnType();
        }

        return static::getDocReturnType($reflection);
    }

    public static function hasDocReturnType(\ReflectionMethod|\ReflectionFunction $reflection): bool
    {
        return static::getDocReturnType($reflection) !== null;
    }

    public static function hasReturnType(\ReflectionMethod|\ReflectionFunction $reflection): bool
    {
        return $reflection->hasReturnType() || static::hasDocReturnType($reflection);
    }

    /**
     * @throws \ReflectionException
     */
    public static function isEnum(string|\ReflectionClass $class): bool
    {
        return static::ensureClassReflected($class)->isEnum();
    }

    /**
     * @throws \ReflectionException
     */
    public static function getEnumValues(\ReflectionEnum|string $enum): array
    {
        $enum = static::ensureEnumReflected($enum);

        if (!$enum->isBacked()) {
            return [];
        }

        return collect($enum->getCases())
            ->filter(fn(\ReflectionEnumBackedCase $item) => $item->isEnumCase())
            ->map(fn(\ReflectionEnumBackedCase $item) => $item->getBackingValue())
            ->toArray();
    }

    public static function enumValuesToTypescript(array $values): string
    {
        return join(
            ' | ',
            array_map(fn(string|int $item) => is_string($item) ? '"' . $item . '"' : $item, $values)
        );
    }

    /**
     * @param callable|\ReflectionMethod|\ReflectionFunction $reflection
     * @return callable|\ReflectionFunction|\ReflectionMethod|\Reflector
     * @throws \ReflectionException
     */
    public static function ensureMethodOrFunctionReflected(callable|\ReflectionMethod|\ReflectionFunction $reflection): \ReflectionFunction|\Reflector|\ReflectionMethod|callable
    {
        return match (true) {
            $reflection instanceof \Reflector => $reflection,
            is_string($reflection) && !Str::contains($reflection, ['@', '::']) => static::ensureFunctionReflected($reflection),
            default => static::ensureMethodReflected($reflection),
        };
    }

    /**
     * @throws \ReflectionException
     */
    public static function convertReturnTypeToTypeScript(\ReflectionType|string|null $type): array|string|TypeScriptTypes
    {
        if ($type === null) {
            return TypeScriptTypes::Null;
        }

        if (is_string($type)) {
            return static::convertBuildInTypeToTypescript($type);
        }

        if ($type instanceof \ReflectionNamedType) {
            $returnTypes[] = static::convertBuildInTypeToTypescript($type->getName());

            if ($type->allowsNull()) {
                $returnTypes[] = TypeScriptTypes::Null;
            }

            return count($returnTypes) === 1 ? $returnTypes[0] : $returnTypes;
        }

        if ($type instanceof \ReflectionUnionType) {
            $returnTypes = array_map([static::class, 'convertReturnTypeToTypeScript'], $type->getTypes());

            if ($type->allowsNull()) {
                $returnTypes[] = TypeScriptTypes::Null;
            }

            return $returnTypes;
        }

        if ($type instanceof \ReflectionIntersectionType) {
            return join(' & ', array_map([static::class, 'convertReturnTypeToTypeScript'], $type->getTypes()));
        }

        return TypeScriptTypes::Any;
    }

    /**
     * @throws \ReflectionException
     */
    public static function convertBuildInTypeToTypescript(string $type): array|string|TypeScriptTypes
    {
        if (str_contains($type, '\\')) {
            $reflection = static::ensureClassReflected($type);

            if (Helper::classInstanceOf($reflection, Model::class)) {
                return Helper::classToDotClass($type);
            }
            if (Helper::classIsEnum($reflection)) {
                return static::getEnumValues($type);
            }

            return TypeScriptTypes::Any;
        }

        return match ($type) {
            'string' => TypeScriptTypes::String,
            'int', 'float' => TypeScriptTypes::Number,
            'array' => [TypeScriptTypes::Array, TypeScriptTypes::Object],
            'bool', 'boolean' => TypeScriptTypes::Boolean,
            default => TypeScriptTypes::Any
        };
    }
}
