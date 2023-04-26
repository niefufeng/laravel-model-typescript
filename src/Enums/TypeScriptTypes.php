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
}
