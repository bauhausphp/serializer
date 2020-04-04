<?php

declare(strict_types=1);

namespace Test\Serializer\Fixture\Dto;

use Test\Serializer\Fixture\Vo\Age;
use Test\Serializer\Fixture\Vo\Email;
use Test\Serializer\Fixture\Vo\Height;
use Test\Serializer\Fixture\Vo\IpAddress;

class CreateUser
{
    /** @var string */
    private $name;

    /** @var IpAddress */
    private $ipAddress;

    /** @var Email */
    private $email;

    /** @var Age */
    private $age;

    /** @var Height */
    private $height;

    public function __construct(string $name, IpAddress $ipAddress, Email $email, Age $age, Height $height)
    {
        $this->name = $name;
        $this->ipAddress = $ipAddress;
        $this->email = $email;
        $this->age = $age;
        $this->height = $height;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getIpAddress(): IpAddress
    {
        return $this->ipAddress;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function getAge(): Age
    {
        return $this->age;
    }

    public function getHeight(): Height
    {
        return $this->height;
    }
}
