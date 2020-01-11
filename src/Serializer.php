<?php

declare(strict_types=1);

namespace Serializer;

use ReflectionException;
use Serializer\Exception\ClassMustHaveAConstructor;
use Serializer\Exception\MissingOrInvalidProperty;
use Serializer\Exception\UnableToLoadOrCreateCacheClass;

abstract class Serializer
{
    /** @var Hydrator[] */
    private $factories = [];

    /** @var ClassFactory */
    private $classFactory;

    /**
     * @param mixed $data
     * @return mixed[]|object|null
     * @throws MissingOrInvalidProperty
     */
    abstract public function deserialize($data, string $class);

    /**
     * @param mixed[]|object|null $data
     * @return mixed
     */
    abstract public function serialize($data);

    public function __construct(ClassFactory $classFactory)
    {
        $this->classFactory = $classFactory;
    }

    /**
     * @param mixed[]|object|null $data
     * @return mixed[]|object|null
     * @throws ClassMustHaveAConstructor
     * @throws ReflectionException
     * @throws UnableToLoadOrCreateCacheClass
     * @throws MissingOrInvalidProperty
     */
    public function deserializeData($data, string $class)
    {
        if (null === $data) {
            return null;
        }

        if (true === is_array($data)) {
            return array_map(function (object $item) use ($class) {
                return $this->deserializeData($item, $class);
            }, $data);
        }

        $factory = $this->loadOrCreateFactory($class);

        return $factory->fromRawToHydrated($data);
    }

    /**
     * @param mixed $data
     * @return mixed[]
     * @throws ClassMustHaveAConstructor
     * @throws ReflectionException
     * @throws UnableToLoadOrCreateCacheClass
     */
    public function serializeData($data): ?array
    {
        if (null === $data) {
            return null;
        }

        if (true === is_array($data)) {
            return array_map(function ($object): ?array {
                return $this->serializeData($object);
            }, $data);
        }

        $class = get_class($data);

        $factory = $this->loadOrCreateFactory($class);

        return $factory->fromHydratedToRaw($data);
    }

    /**
     * @throws ClassMustHaveAConstructor
     * @throws UnableToLoadOrCreateCacheClass
     * @throws ReflectionException
     */
    private function loadOrCreateFactory(string $class): Hydrator
    {
        if (false === isset($this->factories[$class])) {
            $this->factories[$class] = $this->classFactory->createInstance($this, $class);
        }

        return $this->factories[$class];
    }
}
