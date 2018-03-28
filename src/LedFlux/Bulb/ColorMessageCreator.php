<?php

namespace TheJawker\ControlStuff\LedFlux\Bulb;

use TheJawker\ControlStuff\ByteArray;
use TheJawker\ControlStuff\LedFlux\ColorSetting;

class ColorMessageCreator
{
    /**
     * @var ColorSetting
     */
    private $color;
    /**
     * @var Bulb
     */
    private $bulb;

    public function __construct(Bulb $bulb, ColorSetting $color)
    {
        $this->color = $color;
        $this->bulb = $bulb;
    }

    public function forLedenetOriginal()
    {
        return (new ByteArray())
            ->push(0x56)
            ->merge($this->color->toRgbArray())
            ->push(0xaa)
            ->toArray();
    }

    public function forLedenet($persist)
    {
        // All other devices
        $header = $persist ? [0x31] : [0x41];

        $byteArray = new ByteArray($header);

        $byteArray->merge($this->color->toRgbwArray());

        if ($this->bulb->isLedenet()) {
            // LEDENET devices support two white outputs for cold and warm. We set
            // the second one here - if we're only setting a single white value,
            // we set the second output to be the same as the first
            $byteArray->push($this->color->getWhiteWithFallback(0));
        }

        // Write mask, default to writing color and shites simultaneously
        $writeMask = $this->getWriteMask();

        $byteArray->merge([
            $writeMask,
            0x0f,
        ]);

        return $byteArray->toArray();
    }

    /**
     * @return int
     */
    private function getWriteMask(): int
    {
        $writeMask = 0x00;

        // RgbwProtocol devices always overwrite both color & whites.
        if ($this->bulb->rgbwProtocol) {
            return $writeMask;
        }

        if (!$this->color->containsWhite()) {
            $writeMask |= 0xf0; // Mask out whites.
        } elseif (!$this->color->containsColor()) {
            $writeMask |= 0x0f; // Mask out colors.
        }

        return $writeMask;
    }
}