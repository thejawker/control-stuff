<?php

namespace TheJawker\ControlStuff\LedFlux\Bulb;

class BulbStateResolver
{
    /**
     * @var Bulb
     */
    private $bulb;
    private $response;

    public function __construct(Bulb $bulb)
    {
        $this->bulb = $bulb;
    }

    public static function forBulb(Bulb $bulb)
    {
        return new self($bulb);
    }

    public function fromResponse(LedNetOriginalResponse $response)
    {
        $this->response = $response;
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

        // Devices that use the original LEDNET protocol
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
        $this->bulb->mode = $this->determineMode($wwLevel, $pattern);
    }

    public function determineMode($warmWhiteLevel, $patternCode)
    {
        $mode = 'unknown';

        if (in_array($patternCode, [0x61, 0x62])) {
            if ($this->bulb->rgbwCapable) {
                $mode = 'color';
            } elseif ($warmWhiteLevel != 0) {
                $mode = 'ww';
            } else {
                $mode = 'color';
            }
        } elseif ($patternCode === 0x60) {
            $mode = 'custom';
        } elseif ($patternCode === 0x41) {
            $mode = 'color';
        } elseif (PresetPattern::isValid($patternCode)) {
            $mode = 'preset';
        } elseif (BuiltInTimer::isValid($patternCode)) {
            $mode = BuiltInTimer::getName($patternCode);
        }

        return $mode;
    }
}