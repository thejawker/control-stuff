<?php

namespace TheJawker\ControlStuff\Test;

use TheJawker\ControlStuff\LedFlux\BulbScanner;

class BulbScannerTest extends TestCase
{
    /** @test */
    public function can_scan()
    {
        $scanner = new BulbScanner();

        $scanner->scan(1);
    }
}