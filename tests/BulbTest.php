<?php

namespace TheJawker\ControlStuff\Test;


use TheJawker\ControlStuff\LedFlux\Bulb;

class BulbTest extends TestCase
{
    /** @test */
    public function a_bulb_can_be_instantiated_from_ip_alone()
    {
        $bulb  = new Bulb('192.168.178.24');

        $this->assertInstanceOf(Bulb::class, $bulb);

        dd($bulb);
    }
}