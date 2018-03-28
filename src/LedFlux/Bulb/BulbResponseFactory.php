<?php

namespace TheJawker\ControlStuff\LedFlux\Bulb;

use RuntimeException;

class BulbResponseFactory
{
    public static function create(array $responseData)
    {
        if ($responseData[1] === 0x25 ||
            $responseData[1] === 0x27 ||
            $responseData[1] === 0x35
        ) {
            return new LedenetResponse($responseData);
        }

        if ($responseData[1] === 0x01) {
            return new LedNetOriginalResponse($responseData);
        }

        throw new RuntimeException("Couldn't find a suiting Response");
    }
}