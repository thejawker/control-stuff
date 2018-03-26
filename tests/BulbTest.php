<?php

namespace TheJawker\ControlStuff\Test;


use TheJawker\ControlStuff\LedFlux\Bulb;

class BulbTest extends TestCase
{
    /** @test */
    public function a_bulb_can_be_instantiated_from_ip_alone()
    {
        $bulb  = new Bulb('192.168.178.15');
        $bulb->toggle();

        $bulb  = new Bulb('192.168.178.24');
        $bulb->toggle();

        $this->assertInstanceOf(Bulb::class, $bulb);
    }
}