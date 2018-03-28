<?php

namespace TheJawker\ControlStuff\LedFlux\Bulb;

use Primal\Color\RGBColor;
use RuntimeException;
use TheJawker\ControlStuff\LedFlux\Color;
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
        retryCall($retry, function ($retry) {
            $response = $this->queryState($retry);

            if (!$response || count($response) < $this->queryLength) {
                $this->isOn = false;
                return false;
            }

            return BulbStateResolver::forBulb($this)->resolve($response);
//            return BulbStateResolver::forBulb($this)->fromResponse(
//                new LedNetOriginalResponse($response)
//            );
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
        if ($this->isLedenetOriginal() || $ledType === 'LEDENET_ORIGINAL') {
            $message = [0xef, 0x01, 0x77];
            $ledType = 'LEDENET_ORIGINAL';
        }

        $this->connect();
        $this->sendMessage($message);
        $rx = $this->socket->readMessage($this->queryLength);

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
        if ($this->isLedenetOriginal()) {
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
        $rx = $this->socket->readMessage(2);

        // If any response is received, use the default protocol.
        if (count($rx) === 2) {
            $this->queryLength = 14;
            return;
        }

        // If no response from default received, try the original protocol.
        $this->sendMessage([0xef, 0x01, 0x77]);
        $rx = $this->socket->readMessage(2);

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

    public function setRgb(int $red, int $green, int $blue, bool $persist = true, $brightness = null, int $retry = 2)
    {
        $color = new Color($red, $green, $blue);
        $this->setRgbw($color, $persist, $brightness, $retry);
    }

    public function setColor($color, int $retry = 2)
    {
        $this->setRgbw($color, true, null, $retry);
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
        return $this->isLedenet() ? $this->rawState[9] : 0;
    }

    public function getColdWhite(): int
    {
        return $this->isLedenet() ? $this->rawState[11] : 0;
    }

    public function setRgbw(Color $color, bool $persist = true, $brightness = null, int $retry = 2)
    {
        if ($this->supportsRgbw($color)) {
            throw new RuntimeException("RGBW command sent to non-RGBW device");
        }

        if ($brightness !== null) {
            $color->changeBrightness($brightness);
        }

        $colorMessageCreator = new ColorMessageCreator($this, $color);

        // The original LEDENET protocol
        if ($this->isLedenetOriginal()) {
            $message = $colorMessageCreator->forLedenetOriginal();
        } else {
            $message = $colorMessageCreator->forLedenet($persist);
        }

        $this->sendMessage($message);
    }

    /**
     * @param Color $color
     * @return bool
     */
    private function supportsRgbw(Color $color): bool
    {
        return $color->utilizesRgbw() && !$this->rgbwCapable;
    }

    /**
     * @return bool
     */
    private function isLedenetOriginal(): bool
    {
        return $this->protocol === 'LEDENET_ORIGINAL';
    }

    /**
     * @return bool
     */
    public function isLedenet(): bool
    {
        return $this->protocol === 'LEDENET';
    }
}