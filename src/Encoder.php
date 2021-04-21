<?php

declare(strict_types=1);

namespace Serializer;

/**
 * @template T of object
 */
abstract class Encoder
{
    private Serializer $serializer;

    /**
     * @return mixed
     */
    abstract public function encode(object $object);

    public function __construct(Serializer $serializer)
    {
        $this->serializer = $serializer;
    }

    public function serializer(): Serializer
    {
        return $this->serializer;
    }

    public function isCollection(): bool
    {
        return false;
    }
}
