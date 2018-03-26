<?php

namespace TheJawker\ControlStuff\Test;


use TheJawker\ControlStuff\LedFlux\Bulb;

class BulbTest extends TestCase
{
    /** @test */
    public function a_bulb_can_be_instantiated_from_ip_alone()
    {
        $bulb  = new Bulb('192.168.178.15');

        $this->assertInstanceOf(Bulb::class, $bulb);
        $this->assertEquals('192.168.178.15', $bulb->ip);
        $this->assertEquals('600194A0653F', $bulb->id);
        $this->assertEquals('AK001-ZJ200', $bulb->model);
    }
}