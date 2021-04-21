<?php

declare(strict_types=1);

namespace Serializer\Builder;

use IteratorAggregate;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Serializer\Exception\ArrayPropertyMustHaveAnArrayAnnotation;
use Serializer\Exception\ArrayPropertyMustHaveATypeAnnotation;
use Serializer\Exception\ClassMustHaveAConstructor;
use Serializer\Exception\IterableMustHaveOneParameterOnly;
use Serializer\Exception\PropertyHasNoGetter;
use Serializer\Exception\PropertyMustHaveAType;
use Serializer\Exception\ValueObjectMustHaveScalarValue;

class ClassAnalyzer
{
    private string $className;
    private ReflectionClass $class;
    private ReflectionMethod $constructor;

    /**
     * @throws ClassMustHaveAConstructor
     * @throws ReflectionException
     */
    public function __construct(string $className)
    {
        $class = new ReflectionClass($className);
        $constructor = $class->getConstructor();

        if (!$constructor instanceof ReflectionMethod) {
            throw new ClassMustHaveAConstructor($className);
        }

        $this->className = $className;
        $this->class = $class;
        $this->constructor = $constructor;
    }

    public function analyze(): ClassDefinition
    {
        $isCollection = $this->class->implementsInterface(IteratorAggregate::class);
        $isValueObject = $this->class->hasMethod('__toString') && $this->constructor->getNumberOfParameters() === 1;

        $properties = array_map(function (ReflectionParameter $param) use ($isCollection, $isValueObject) {
            return $this->createProperty($param, $isCollection, $isValueObject);
        }, $this->constructor->getParameters());

        return new ClassDefinition($this->className, $isCollection, $isValueObject, ...$properties);
    }

    /**
     * @throws ArrayPropertyMustHaveATypeAnnotation
     * @throws ArrayPropertyMustHaveAnArrayAnnotation
     * @throws PropertyMustHaveAType
     * @throws PropertyHasNoGetter
     * @throws IterableMustHaveOneParameterOnly
     * @throws ValueObjectMustHaveScalarValue
     * @throws ReflectionException
     */
    private function createProperty(ReflectionParameter $param, bool $isCollection, bool $isValueObject): ClassProperty
    {
        $name = $param->getName();
        $type = $this->searchParamType($param);
        $defaultValue = ($param->isDefaultValueAvailable() ? (string) $param->getDefaultValue() : null) ?: null;
        $getter = $isValueObject ? '__toString' : $this->searchParamGetter($param, $type, $isCollection);
        $isArgument = $param->isVariadic();

        $property = new ClassProperty($this->className, $name, $type, $defaultValue, $isArgument, $getter);

        if ($isValueObject && false === $property->isScalar()) {
            throw new ValueObjectMustHaveScalarValue($property, $this->class);
        }

        return $property;
    }

    /**
     * @throws ArrayPropertyMustHaveATypeAnnotation
     * @throws ArrayPropertyMustHaveAnArrayAnnotation
     * @throws PropertyMustHaveAType
     */
    private function searchParamType(ReflectionParameter $param): string
    {
        $refType = $param->getType();
        assert($refType instanceof ReflectionNamedType);
        $type = $refType->getName();

        if ('' === $type) {
            throw new PropertyMustHaveAType($param, $this->class);
        }

        if (true === $this->isScalar($type)) {
            return $type;
        }

        if (true === $this->isArray($type)) {
            return $this->searchArrayType($param);
        }

        return $type;
    }

    private function isScalar(string $type): bool
    {
        return in_array($type, ['int', 'float', 'string', 'bool']);
    }

    private function isArray(string $type): bool
    {
        return $type === 'array';
    }

    /**
     * @throws ArrayPropertyMustHaveATypeAnnotation
     * @throws ArrayPropertyMustHaveAnArrayAnnotation
     */
    private function searchArrayType(ReflectionParameter $param): string
    {
        $type = $this->searchTypeOnDocComment($param);
        $namespace = $this->searchNamespace($type);

        return sprintf('%s%s[]', $namespace, $type);
    }

    /**
     * @throws ArrayPropertyMustHaveATypeAnnotation
     * @throws ArrayPropertyMustHaveAnArrayAnnotation
     */
    private function searchTypeOnDocComment(ReflectionParameter $param): string
    {
        $docComment = $this->constructor->getDocComment() ?: '';
        $pattern = sprintf('/\@param(.*)\$%s/', $param->getName());

        preg_match($pattern, $docComment, $matches);
        $type = trim($matches[1] ?? '');

        if ('' === $type) {
            throw new ArrayPropertyMustHaveATypeAnnotation($param, $this->class);
        }

        if (false === strpos($type, '[]')) {
            throw new ArrayPropertyMustHaveAnArrayAnnotation($param, $this->class, $type);
        }

        return str_replace('[]', '', $type);
    }

    private function searchNamespace(string $type): string
    {
        $parts = explode('\\', $type);
        $subNs = reset($parts);

        if ('' === $subNs) {
            return trim($type, '\\');
        }

        $file = file($this->class->getFileName() ?: '') ?: [];
        $lines = array_slice($file, 0, (int) $this->class->getStartLine());

        $pattern = sprintf('/use(.*)%s;/', $subNs);
        preg_match($pattern, implode(PHP_EOL, $lines), $matches);

        $match = trim($matches[0] ?? '');
        $namespace = trim($matches[1] ?? '');

        if ('' === $namespace && '' === $match && '' !== $subNs) {
            $namespace = sprintf('%s\\', $this->class->getNamespaceName());
        }

        return $namespace;
    }

    /**
     * @throws PropertyHasNoGetter
     * @throws IterableMustHaveOneParameterOnly
     */
    private function searchParamGetter(ReflectionParameter $param, string $type, bool $isCollection): string
    {
        if ($isCollection) {
            return $this->getIteratorGetter();
        }

        if ($type === 'bool') {
            return $this->searchGetterForBoolean($param);
        }

        $getter = sprintf('get%s', ucfirst($param->getName()));

        if (false === $this->class->hasMethod($getter)) {
            return '';
        }

        return $getter;
    }

    /**
     * @throws PropertyHasNoGetter
     */
    private function searchGetterForBoolean(ReflectionParameter $param): string
    {
        $name = $param->getName();

        $isPrefix = sprintf('is%s', ucfirst($name));

        if (true === $this->class->hasMethod($isPrefix)) {
            return $isPrefix;
        }

        $hasPrefix = sprintf('has%s', ucfirst($name));

        if (true === $this->class->hasMethod($hasPrefix)) {
            return $hasPrefix;
        }

        throw new PropertyHasNoGetter($this->className, $name, true);
    }

    /**
     * @throws IterableMustHaveOneParameterOnly
     */
    private function getIteratorGetter(): string
    {
        $params = $this->constructor->getParameters();

        if (count($params) !== 1) {
            throw new IterableMustHaveOneParameterOnly($this->class->getName(), count($params));
        }

        return 'getIterator';
    }
}
