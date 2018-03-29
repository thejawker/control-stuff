<?php

namespace TheJawker\ControlStuff\LedFlux;

use Primal\Color\RGBColor;

class ColorSetting
{
    public $red;
    public $green;
    public $blue;
    public $white;
    public $white2;

    public function __construct(
        int $red = null,
        int $green = null,
        int $blue = null,
        int $white = null,
        int $white2 = null
    )
    {
        $this->red = $red;
        $this->green = $green;
        $this->blue = $blue;
        $this->white = $white;
        $this->white2 = $white2;
    }

    public static function fromString($string)
    {
        if (preg_match("/rgb\((\d{1,3}),\s{0,1}(\d{1,3}),\s{0,1}(\d{1,3})\)/x", $string, $colors)) {
            [$_, $red, $green, $blue] = $colors;
            return new self($red, $green, $blue);
        }
    }

    public function containsColor()
    {
        return ($this->red || $this->green || $this->blue);
    }

    public function containsWhite()
    {
        return ($this->white || $this->white2);
    }

    public function utilizesRgbw()
    {
        return $this->containsColor() && $this->containsWhite();
    }

    public function changeBrightness($brightness)
    {
        $hsv = $this->getRgbColor()->toHSV();
        $hsv->value = $brightness;
        $this->setRgbColor($hsv->toRGB());
    }

    private function getRgbColor(): RGBColor
    {
        return new RGBColor($this->red, $this->green, $this->blue);
    }

    private function setRgbColor(RGBColor $color)
    {
        $this->red = $color->red;
        $this->green = $color->green;
        $this->blue = $color->blue;
    }

    public function toRgbArray()
    {
        return [
            (int) $this->getRed(),
            (int) $this->getGreen(),
            (int) $this->getBlue(),
        ];
    }

    public function getRed(): int
    {
        return (int) $this->red;
    }

    public function getGreen(): int
    {
        return (int) $this->green;
    }

    public function getBlue(): int
    {
        return (int) $this->blue;
    }

    public function toRgbwArray()
    {
        $colors = $this->toRgbArray();
        $colors[] = $this->white;
        return $colors;
    }

    public function getWhiteWithFallback(int $fallback = 0)
    {
        if ($this->white2) {
            return (int) $this->white2;
        }

        if ($this->white) {
            return (int) $this->white;
        }

        return $fallback;
    }
}