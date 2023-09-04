<?php

namespace NieFufeng\LaravelModelTypescript\Enums;

enum TypeScriptTypes: string
{
    case String = 'string';
    case Number = 'number';
    case Boolean = 'boolean';
    case Any = 'any';
    case Null = 'null';
    case Array = 'array';
    case Object = 'object';

    public function toString(): string
    {
        if ($this === self::Array) {
            return 'Array<any>';
        }

        return $this->value;
    }
}
