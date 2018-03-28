<?php

namespace TheJawker\ControlStuff\LedFlux;

use Primal\Color\RGBColor;
use RuntimeException;
use TheJawker\ControlStuff\Socket;

class Bulb
{
    public $ip = null;
    public $port = null;
    public $timeout = null;
    public $socket = null;
    public $isOn = false;
    public $queryLength = 0;
    public $rgbwProtocol = false;
    public $rgbwCapable = false;
    public $protocol = null;
    public $rawState = null;
    public $mode = null;
    public $lock = null;
    public $useChecksum = true;

    public function __construct(string $ip, int $port = 5577, int $timeout = 5)
    {
        $this->ip = $ip;
        $this->port = $port;
        $this->timeout = $timeout;
        $this->socket = new Socket();

        $this->connect(2);
        $this->updateState();
    }

    public function connect(int $retry = 2)
    {
        retryCall($retry, function () {
            $this->socket->closeSocket();
            return $this->socket->openStream($this->ip, $this->port);
        });
    }

    private function updateState(int $retry = 2)
    {
        retryCall($retry, function() use ($retry) {
            $rx = $this->queryState($retry);

            if (!$rx || count($rx) < $this->queryLength) {
                $this->isOn = false;
                return false;
            }

            return StateResolver::forBulb($this)->resolve($rx);
        });
    }

    private function queryState(int $retry = 2, $ledType = null)
    {
        if ($this->queryLength === 0) {
            $this->determineQueryLength();
        }

        // Default value;
        $message = [0x81, 0x8a, 0x8b];

        // Alternative for original protocol
        if ($this->protocol === 'LEDENET_ORIGINAL' || $ledType === 'LEDENET_ORIGINAL') {
            $message = [0xef, 0x01, 0x77];
            $ledType = 'LEDENET_ORIGINAL';
        }

        $this->connect();
        $this->sendMessage($message);
        $rx = $this->readMessage($this->queryLength);

        if ($rx === null || count($rx) < $this->queryLength) {
            if ($retry < 1) {
                $this->isOn = false;
                return $rx;
            }
            return $this->queryState(max($retry - 1, 0), $ledType);
        }

        return $rx;
    }

    private function changeState(int $retry, bool $turnOn = true)
    {
        if ($this->protocol === 'LEDENET_ORIGINAL') {
            $messageOn = [0xcc, 0x23, 0x33];
            $messageOff = [0xcc, 0x24, 0x33];
        } else {
            $messageOn = [0x71, 0x23, 0x0f];
            $messageOff = [0x71, 0x24, 0x0f];
        }

        $message = $turnOn ? $messageOn : $messageOff;

        $this->sendMessage($message);
    }

    public function turnOn(int $retry = 2)
    {
        $this->isOn = true;
        $this->changeState($retry, true);
    }

    public function turnOff(int $retry = 2)
    {
        $this->isOn = false;
        $this->changeState($retry, false);
    }

    public function toggle(int $retry = 2)
    {
        $this->changeState($retry, !$this->isOn);
    }

    private function determineQueryLength(int $retry = 2)
    {
        // Determine the type of protocol based on the first 2 bytes.
        $this->sendMessage([0x81, 0x8a, 0x8b]);
        $rx = $this->readMessage(2);

        // If any response is received, use the default protocol.
        if (count($rx) === 2) {
            $this->queryLength = 14;
            return;
        }

        // If no response from default received, try the original protocol.
        $this->sendMessage([0xef, 0x01, 0x77]);
        $rx = $this->readMessage(2);

        if ($rx[1] === 0x01) {
            $this->protocol = 'LEDENET_ORIGINAL';
            $this->useChecksum = false;
            $this->queryLength = 11;
            return;
        } else {
            $this->useChecksum = true;
        }

        if ($rx === null && $retry > 0) {
            $this->determineQueryLength(max($retry - 1, 0));
        }
    }

    public function getRgb()
    {
        if ($this->mode !== 'color') {
            return [255, 255, 255];
        }

        return [
            $this->rawState[6],
            $this->rawState[7],
            $this->rawState[8],
        ];
    }

    public function getWarmWhite255()
    {
        if ($this->mode !== 'ww') {
            return 255;
        }
        return $this->getBrightness();
    }

    private function sendMessage($bytes)
    {
        // Calculate the checksum of the byte array and add to end
        if ($this->useChecksum) {
            $checksum = array_sum($bytes) & 0xFF;
            $bytes[] = $checksum;
        }

        $this->socket->sendMessage($bytes);
    }

    private function readMessage(int $expected)
    {
        return $this->socket->readMessage($expected);
    }

    public function setRgb(int $red, int $green, int $blue, bool $persist = true, $brightness = null, int $retry = 2)
    {
        $this->setRgbw($red, $green, $blue, null, $persist, $brightness, $retry);
    }

    public function determineMode($warmWhiteLevel, $patternCode)
    {
        $mode = 'unknown';

        if (in_array($patternCode, [0x61, 0x62])) {
            if ($this->rgbwCapable) {
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

    public function getBrightness(): int
    {
        if ($this->mode === 'ww') {
            return (int) $this->rawState[9];
        }

        return (new RGBColor($this->getRgb()))
            ->toHSV()->value;
    }

    public function getWarmWhite(): int
    {
        if ($this->protocol === 'LEDENET') {
            return $this->rawState[9];
        }

        return 0;
    }

    public function getColdWhite(): int
    {
        if ($this->protocol === 'LEDENET') {
            return $this->rawState[11];
        }

        return 0;
    }

    public function calculateBrightness(array $rgb, int $level)
    {
        [$red, $green, $blue] = $rgb;
        $hsv = (new RGBColor($red, $green, $blue))->toHSV();
        $hsv->value = $level;
        $rgb = $hsv->toRGB();
        return [
            $rgb->red,
            $rgb->green,
            $rgb->blue
        ];
    }

    public function setRgbw(
        int $red = null,
        int $green = null,
        int $blue = null,
        int $white = null,
        bool $persist = true,
        $brightness = null,
        int $retry = 2,
        $white2 = null
    )
    {
        if (
            ($red || $green || $blue) &&
            ($white || $white2) &&
            !$this->rgbwCapable
        ) {
            throw new RuntimeException("RGBW command sent to non-RGBW device");
        }

        // sample message for original LEDENET protocol (w/o checksum at end)
        //  0  1  2  3  4
        // 56 90 fa 77 aa
        //  |  |  |  |  |
        //  |  |  |  |  terminator
        //  |  |  |  blue
        //  |  |  green
        //  |  red
        //  head

        // sample message for 8-byte protocols (w/ checksum at end)
        //  0  1  2  3  4  5  6
        // 31 90 fa 77 00 00 0f
        //  |  |  |  |  |  |  |
        //  |  |  |  |  |  |  terminator
        //  |  |  |  |  |  write mask / white2 (see below)
        //  |  |  |  |  white
        //  |  |  |  blue
        //  |  |  green
        //  |  red
        //  persistence (31 for true / 41 for false)
        //
        // byte 5 can have different values depending on the type
        // of device:
        // For devices that support 2 types of white value (warm and cold
        // white) this value is the cold white value. These use the LEDENET
        // protocol. If a second value is not given, reuse the first white value.
        //
        // For devices that cannot set both rbg and white values at the same time
        // (including devices that only support white) this value
        // specifies if this command is to set white value (0f) or the rgb
        // value (f0).
        //
        // For all other rgb and rgbw devices, the value is 00

        // sample message for 9-byte LEDENET protocol (w/ checksum at end)
        //  0  1  2  3  4  5  6  7
        // 31 bc c1 ff 00 00 f0 0f
        //  |  |  |  |  |  |  |  |
        //  |  |  |  |  |  |  |  terminator
        //  |  |  |  |  |  |  write mode (f0 colors, 0f whites, 00 colors & whites)
        //  |  |  |  |  |  cold white
        //  |  |  |  |  warm white
        //  |  |  |  blue
        //  |  |  green
        //  |  red
        //  persistence (31 for true / 41 for false)
        //

        if ($brightness !== null) {
            [$red, $green, $blue] = $this->calculateBrightness([$red, $green, $blue], $brightness);
        }

        // The original LEDENET protocol
        if ($this->protocol === 'LEDENET_ORIGINAL') {
            $message = [
                0x56,
                (int) $red,
                (int) $green,
                (int) $blue,
                0xaa
            ];
        } else {
            // All other devices

            // Assemble the message
            if ($persist) {
                $message = [0x31];
            } else {
                $message = [0x41];
            }

            $message[] = $red ? (int) $red : 0;
            $message[] = $green ? (int) $green : 0;
            $message[] = $blue ? (int) $blue : 0;
            $message[] = $white ? (int) $white : 0;

            if ($this->protocol === 'LEDENET') {
                // LEDENET devices support two white outputs for cold and warm. We set
                // the second one here - if we're only setting a single white value,
                // we set the second output to be the same as the first

                if ($white2) {
                    $message[] = (int) $white2;
                } elseif ($white) {
                    $message[] = (int) $white;
                } else {
                    $message[] = 0;
                }
            }

            // Write mask, default to writing color and shites simultaneously
            $writeMask = 0x00;

            // RgbwProtocol devices always overwrite both color & whites.
            if (!$this->rgbwProtocol) {
                if (!$white && !$white2) {
                    // Mask out whites.
                    $writeMask |= 0xf0;
                } elseif (!$red && !$green && !$blue) {
                    // Mask out colors.
                    $writeMask |= 0x0f;
                }
            }

            $message[] = $writeMask;

            // Message terminator.
            $message[] = 0x0f;

            $this->sendMessage($message);
        }
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
            $this->rgbwProtocol = true;
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
            $this->rgbwCapable = true;
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
            $this->protocol = 'LEDENET';
        }

        if ($rx[1] === 0x01) {
            $this->protocol = 'LEDENET_ORIGINAL';
        }
    }

    /**
     * @param $rx
     */
    private function detectChecksum($rx): void
    {
        if ($rx[1] === 0x01) {
            $this->useChecksum = false;
        }
    }

    /**
     * @param $rx
     */
    private function detectPowerState($rx): void
    {
        $powerState = $rx[2];

        if ($powerState === 0x23) {
            $this->isOn = true;
        } elseif ($powerState === 0x24) {
            $this->isOn = false;
        }
    }

    /**
     * @param $rx
     */
    private function detectMode($rx): void
    {
        $pattern = $rx[3];
        $wwLevel = $rx[9];
        $this->mode = $this->determineMode($wwLevel, $pattern);
    }
}