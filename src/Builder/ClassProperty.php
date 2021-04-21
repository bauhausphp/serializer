<?php

declare(strict_types=1);

namespace Serializer\Builder;

use Serializer\Exception\PropertyHasNoGetter;

class ClassProperty
{
    private string $class;
    private string $name;
    private string $type;
    private ?string $defaultValue;
    private string $getter;
    private bool $isArgument;

    public function __construct(
        string $class,
        string $name,
        string $type,
        ?string $defaultValue,
        bool $isArgument,
        string $getter
    ) {
        $this->class = $class;
        $this->name = $name;
        $this->type = $type;
        $this->defaultValue = $defaultValue;
        $this->isArgument = $isArgument;
        $this->getter = $getter;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        if ($this->isArray()) {
            return str_replace('[]', '', $this->type);
        }

        return $this->type;
    }

    public function getDefaultValue(): string
    {
        if (null === $this->defaultValue) {
            return 'null';
        }

        if ($this->isString()) {
            return sprintf("'%s'", $this->defaultValue);
        }

        return $this->defaultValue;
    }

    public function isArgument(): bool
    {
        return $this->isArgument;
    }

    public function getGetter(): string
    {
        if ($this->getter === '') {
            throw new PropertyHasNoGetter($this->class, $this->name);
        }

        return $this->getter;
    }

    public function isScalar(): bool
    {
        return in_array($this->type, ['int', 'float', 'string', 'bool']);
    }

    public function isArray(): bool
    {
        return strpos($this->type, '[]') !== false;
    }

    public function isString(): bool
    {
        return $this->type === 'string';
    }
}
