<?php

declare(strict_types=1);

namespace Serializer;

use ReflectionException;
use Serializer\Exception\ClassMustHaveAConstructor;
use Serializer\Exception\MissingOrInvalidProperty;
use Serializer\Exception\UnableToLoadOrCreateCacheClass;
use Traversable;

abstract class Serializer
{
    /** @var Encoder[] */
    private array $encoders;

    /** @var Decoder[] */
    private array $decoders;

    private EncoderFactory $encoderFactory;
    private DecoderFactory $decoderFactory;

    /**
     * @template T of object
     * @param class-string<T> $class
     * @param mixed $data
     * @return T|array<T>|null
     * @throws MissingOrInvalidProperty
     */
    abstract public function deserialize($data, string $class);

    /**
     * @param mixed[]|object|null $data
     * @return mixed
     */
    abstract public function serialize($data);

    public function __construct(EncoderFactory $encoderFactory, DecoderFactory $decoderFactory)
    {
        $this->encoders = [];
        $this->decoders = [];

        $this->encoderFactory = $encoderFactory;
        $this->decoderFactory = $decoderFactory;
    }

    /**
     * @param mixed[]|object|null $data
     * @template T of object
     * @param class-string<T> $class
     * @return T|array<T>|mixed|null
     * @throws ClassMustHaveAConstructor
     * @throws ReflectionException
     * @throws UnableToLoadOrCreateCacheClass
     * @throws MissingOrInvalidProperty
     */
    public function decode($data, string $class, ?string $propertyName = null)
    {
        if (null === $data) {
            return null;
        }

        $decoder = $this->loadOrCreateDecoder($class);

        if ($decoder->isCollection()) {
            return $decoder->decode($data, $propertyName);
        }

        if (true === is_array($data)) {
            return array_map(function (object $item) use ($class) {
                return $this->decode($item, $class);
            }, $data);
        }

        return $decoder->decode($data, $propertyName);
    }

    /**
     * @param mixed $data
     * @return string[]|mixed[]|null
     * @throws ClassMustHaveAConstructor
     * @throws ReflectionException
     * @throws UnableToLoadOrCreateCacheClass
     */
    public function encode($data)
    {
        if (null === $data) {
            return null;
        }

        if ($data instanceof Traversable) {
            $data = iterator_to_array($data);
        }

        if (true === is_array($data)) {
            return array_map(function ($object) {
                return $this->encode($object);
            }, $data);
        }

        if (false === is_object($data)) {
            return $data;
        }

        $class = get_class($data);
        $encoder = $this->loadOrCreateEncoder($class);

        return $encoder->encode($data);
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return Encoder<T>
     * @throws ClassMustHaveAConstructor
     * @throws UnableToLoadOrCreateCacheClass
     * @throws ReflectionException
     */
    private function loadOrCreateEncoder(string $class): Encoder
    {
        if (false === isset($this->encoders[$class])) {
            $this->encoders[$class] = $this->encoderFactory->createEncoder($this, $class);
        }

        return $this->encoders[$class];
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return Decoder<T>
     * @throws ClassMustHaveAConstructor
     * @throws UnableToLoadOrCreateCacheClass
     * @throws ReflectionException
     */
    private function loadOrCreateDecoder(string $class): Decoder
    {
        if (false === isset($this->decoders[$class])) {
            $this->decoders[$class] = $this->decoderFactory->createDecoder($this, $class);
        }

        return $this->decoders[$class];
    }
}
