<?php

namespace TheJawker\ControlStuff;

class ByteArray
{
    private $array;

    public function __construct(array $bytes = [])
    {
        $this->array = $bytes;
    }

    public function push($byte)
    {
        $this->array[] = $byte;
        return $this;
    }

    public function merge(array $bytes)
    {
        foreach ($bytes as $byte) {
            $this->push($byte);
        }
        return $this;
    }

    public function toArray(): array
    {
        return $this->array;
    }
}