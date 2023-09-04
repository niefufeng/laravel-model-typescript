<?php

namespace NieFufeng\LaravelModelTypescript;

use Illuminate\Support\Collection;
use NieFufeng\LaravelModelTypescript\Enums\TypeScriptTypes;

class TypeScriptDefinition
{
    public function __construct(
        public string                       $name,
        public TypeScriptTypes|array|string $types,
        public bool                         $optional = false,
        public bool                         $readonly = false,
        public bool                         $nullable = false,
        public ?string                      $comment = null,
    )
    {
    }

    protected function typeToString(TypeScriptTypes|array|string $type): string
    {
        if (is_string($type)) {
            return $type;
        }

        if (is_array($type)) {
            return collect(array_map($this->typeToString(...), $type))->join(' | ');
        }

        return $type->toString();
    }

    protected function typeContainsNull(): bool
    {
        if (is_string($this->types)) {
            return $this->types === 'null';
        }

        if (is_string($this->types)) {
            return in_array(TypeScriptTypes::Null, $this->types);
        }

        return $this->types === TypeScriptTypes::Null;
    }

    protected function typesToString(): string
    {
        return collect($this->types)
            ->when(
                $this->nullable && !$this->typeContainsNull(),
                fn(Collection $types) => $types->push(TypeScriptTypes::Null)
            )
            ->map($this->typeToString(...))
            ->unique()
            ->join(' | ', '');
    }

    public function __toString(): string
    {
        return collect($this->name)
            ->when($this->readonly, fn(Collection $definition) => $definition->prepend('readonly '))
            ->when($this->optional, fn(Collection $definition) => $definition->push('?'))
            ->push(': ')
            ->push($this->typesToString())
            ->push(';')
            ->join('');
    }
}
