<?php

namespace TheJawker\ControlStuff\LedFlux;

use ReflectionClass;
use ReflectionException;

class PresetPattern
{
    /**
     * The default Preset Patterns
     *
     * @var int
     */
    const SEVEN_COLOR_CROSS_FADE = 0x25;
    const RED_GRADUAL_CHANGE = 0x26;
    const GREEN_GRADUAL_CHANGE = 0x27;
    const BLUE_GRADUAL_CHANGE = 0x28;
    const YELLOW_GRADUAL_CHANGE = 0x29;
    const CYAN_GRADUAL_CHANGE = 0x2a;
    const PURPLE_GRADUAL_CHANGE = 0x2b;
    const WHITE_GRADUAL_CHANGE = 0x2c;
    const RED_GREEN_CROSS_FADE = 0x2d;
    const RED_BLUE_CROSS_FADE = 0x2e;
    const GREEN_BLUE_CROSS_FADE = 0x2f;
    const SEVEN_COLOR_STROBE_FLASH = 0x30;
    const RED_STROBE_FLASH = 0x31;
    const GREEN_STROBE_FLASH = 0x32;
    const BLUE_STROBE_FLASH = 0x33;
    const YELLOW_STROBE_FLASH = 0x34;
    const CYAN_STROBE_FLASH = 0x35;
    const PURPLE_STROBE_FLASH = 0x36;
    const WHITE_STROBE_FLASH = 0x37;
    const SEVEN_COLOR_JUMPING = 0x38;

    public static function isValid(int $pattern): bool
    {
        if ($pattern < 0x25 || $pattern > 0x38) {
            return false;
        }

        return true;
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