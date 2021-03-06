<?php

declare(strict_types=1);

namespace Test\Serializer\Fixture\Dto\Collection;

use ArrayIterator;
use IteratorAggregate;
use Test\Serializer\Fixture\Dto\Place;

class PlaceIterableCollection implements IteratorAggregate
{
    /** @var Place[] */
    private $places;

    public function __construct(Place ...$places)
    {
        $this->places = new ArrayIterator($places);
    }

    /**
     * @return Place[]
     */
    public function getIterator(): iterable
    {
        return $this->places;
    }
}
