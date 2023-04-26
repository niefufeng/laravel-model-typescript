<?php

namespace NieFufeng\LaravelModelTypescript\Utils;

class ClassHelper
{
    public static function classInstanceOf(string $class, string $instanceOf): bool
    {
        if ($class === $instanceOf) {
            return true;
        }

        try {
            return (new \ReflectionClass($class))->isSubclassOf($instanceOf);
        } catch (\ReflectionException) {
            return false;
        }
    }

    public static function classIsEnum(string $class): bool
    {
        try {
            return (new \ReflectionClass($class))->isEnum();
        } catch (\ReflectionException) {
            return false;
        }
    }

    public static function namespaceClassToDotClass(string $class): string
    {
        return str_replace('\\', '.', $class);
    }

    public static function getDocCommentReturnType(\ReflectionMethod|\ReflectionFunction $reflection): ?string
    {
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

    public static function getReturnType(\ReflectionMethod|\ReflectionFunction $reflection): null|string|\ReflectionType
    {
        if ($reflection->hasReturnType()) {
            return $reflection->getReturnType();
        }

        return static::getDocCommentReturnType($reflection);
    }

    public static function hasDocCommentReturnType(\ReflectionMethod|\ReflectionFunction $reflection): bool
    {
        $docComment = $reflection->getDocComment();

        if ($docComment === false) {
            return false;
        }

        $pattern = '/@return\s+(.*?)[\s\n]/';

        if (preg_match($pattern, $docComment, $matched) !== 1) {
            return false;
        }

        return trim($matched[1]) !== '';
    }

    public static function hasReturnType(\ReflectionMethod|\ReflectionFunction $reflection): bool
    {
        return $reflection->hasReturnType() || static::hasDocCommentReturnType($reflection);
    }

    public static function isEnum(string|\ReflectionClass $class): bool
    {
        if (is_string($class)) {
            $class = new \ReflectionClass($class);
        }

        return $class->isEnum();
    }

    public static function getEnumValues(\ReflectionEnum|string $enum): array|string
    {
        if (is_string($enum)) {
            $enum = new \ReflectionEnum($enum);
        }

        return collect($enum->getConstants())
            ->map(fn($item) => $enum->getBackingType()?->getName() === 'string' ? '"' . $item->value . '"' : $item->value)
            ->join(' | ');
    }
}
