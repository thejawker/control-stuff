<?php

namespace TheJawker\ControlStuff\LedFlux;

class StateResolver
{
    /**
     * @var Bulb
     */
    private $bulb;

    public function __construct(Bulb $bulb)
    {
        $this->bulb = $bulb;
    }

    public static function forBulb(AbstractBulb $bulb)
    {
        return new self($bulb);
    }

    public function resolve(array $byteData)
    {
        $this->detectRgbwProtocol($byteData);
        $this->detectRgbwCapable($byteData);
        $this->detectProtocol($byteData);
        $this->detectChecksum($byteData);
        $this->detectMode($byteData);

        if ($this->bulb->mode === 'unknown') {
            return false;
        }

        $this->detectPowerState($byteData);

        return true;
    }

    /**
     * @param $rx
     */
    private function detectRgbwProtocol($rx): void
    {
        // Devices that don't require a separate rgb/w bit
        if (
            $rx[1] === 0x04 ||
            $rx[1] === 0x33 ||
            $rx[1] === 0x81
        ) {
            $this->bulb->rgbwProtocol = true;
        }
    }

    /**
     * @param $rx
     */
    private function detectRgbwCapable($rx): void
    {
        // Devices that actually support rgbw
        if (
            $rx[1] === 0x04 ||
            $rx[1] === 0x25 ||
            $rx[1] === 0x33 ||
            $rx[1] === 0x81
        ) {
            $this->bulb->rgbwCapable = true;
        }
    }

    /**
     * @param $rx
     */
    private function detectProtocol($rx): void
    {
        // Devices that use an 8-byte protocol
        if ($rx[1] === 0x25 ||
            $rx[1] === 0x27 ||
            $rx[1] === 0x35
        ) {
            $this->bulb->protocol = 'LEDENET';
        }

        if ($rx[1] === 0x01) {
            $this->bulb->protocol = 'LEDENET_ORIGINAL';
        }
    }

    /**
     * @param $rx
     */
    private function detectChecksum($rx): void
    {
        if ($rx[1] === 0x01) {
            $this->bulb->useChecksum = false;
        }
    }

    /**
     * @param $rx
     */
    private function detectPowerState($rx): void
    {
        $powerState = $rx[2];

        if ($powerState === 0x23) {
            $this->bulb->isOn = true;
        } elseif ($powerState === 0x24) {
            $this->bulb->isOn = false;
        }
    }

    /**
     * @param $rx
     */
    private function detectMode($rx): void
    {
        $pattern = $rx[3];
        $wwLevel = $rx[9];
        $this->bulb->mode = $this->bulb->determineMode($wwLevel, $pattern);
    }
}