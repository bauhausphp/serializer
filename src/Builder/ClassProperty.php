<?php

declare(strict_types=1);

namespace Serializer\Builder;

class ClassProperty
{
    /** @var string */
    private $name;

    /** @var string */
    private $type;

    /** @var string|null */
    private $defaultValue;

    /** @var string */
    private $getter;

    /** @var bool */
    private $isArgument;

    public function __construct(string $name, string $type, ?string $defaultValue, bool $isArgument, string $getter)
    {
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
