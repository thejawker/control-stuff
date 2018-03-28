<?php

namespace TheJawker\ControlStuff\Test;

use TheJawker\ControlStuff\LedFlux\Bulb\Bulb;
use TheJawker\ControlStuff\LedFlux\Color;

class BulbTest extends TestCase
{
    /** @test */
    public function a_bulb_can_be_instantiated_from_ip_alone()
    {
        $bulb = new Bulb('192.168.178.15');
        $bulb->setColor($this->randomColor());

        $bulb = new Bulb('192.168.178.24');
        $bulb->setColor($this->randomColor());
    }

    private function randomColor()
    {
        return new Color(rand(0, 255),rand(0, 255),rand(0, 255), rand(0, 100));
    }
}