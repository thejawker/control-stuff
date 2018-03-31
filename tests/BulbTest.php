<?php

namespace TheJawker\ControlStuff\Test;

use TheJawker\ControlStuff\LedFlux\Bulb\Bulb;
use TheJawker\ControlStuff\LedFlux\ColorSetting;

class BulbTest extends TestCase
{
    /** @test */
    public function a_bulb_can_be_instantiated_from_ip_alone()
    {
        $setting = ColorSetting::fromString('rgbw(255,255,255, 100)');

        $bulb = new Bulb('192.168.178.15');
        $bulb->setColor(ColorSetting::fromString('rgb(255,255,255)'));

        $bulb = new Bulb('192.168.178.24');
        $bulb->setColor($this->randomColor());
    }

    private function randomColor()
    {
        return new ColorSetting(rand(0, 255),rand(0, 255),rand(0, 255), rand(0, 100));
    }
}