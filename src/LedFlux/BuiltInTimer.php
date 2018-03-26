<?php

namespace TheJawker\ControlStuff\LedFlux;

use ReflectionClass;
use ReflectionException;

class BuiltInTimer
{
    const SUNRISE = 0xA1;
    const SUNSET = 0xA2;

    public static function isValid(int $byteValue)
    {
        return ($byteValue === self::SUNRISE || $byteValue === self::SUNSET);
    }

    public static function getName(int $pattern)
    {
        $values = array_flip(self::getConstants());
        $name = $values[$pattern];
        return title_case(str_replace('_', ' ', $name));
    }

    public static function getConstants(): ?array
    {
        try {
            return (new ReflectionClass(self::class))
                ->getConstants();
        } catch (ReflectionException $e) {
            return null;
        }
    }
}