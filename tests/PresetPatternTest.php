<?php

namespace TheJawker\ControlStuff\Test;

use TheJawker\ControlStuff\LedFlux\PresetPattern;

class PresetPatternTest extends TestCase
{
    /** @test */
    public function can_return_a_list_of_constants()
    {
        $patterns = PresetPattern::getConstants();

        $this->assertInternalType('array', $patterns);
    }
    
    /** @test */
    public function can_return_the_title_case_of_a_value()
    {
        $pattern = PresetPattern::getName(0x25);

        $this->assertEquals('Seven Color Cross Fade', $pattern);
    }
}