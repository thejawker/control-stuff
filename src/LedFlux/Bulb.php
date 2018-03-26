<?php

namespace TheJawker\ControlStuff\LedFlux;

use TheJawker\ControlStuff\Socket;

class Bulb
{
    public $ip;
    public $port;
    public $timeout;
    public $socket;
    private $isOn;
    private $queryLength;
    private $rgbwProtocol;
    private $rgbwCapable;
    private $protocol;
    private $rawState;
    private $mode;
    private $lock;
    private $useChecksum;

    public function __construct(string $ip, int $port = 5577, int $timeout = 5)
    {
        $this->ip = $ip;
        $this->port = $port;
        $this->timeout = $timeout;
        $this->socket = new Socket();

        $this->protocol = null;
        $this->rgbwCapable = false;
        $this->rgbwProtocol = false;

        $this->rawState = null;
        $this->isOn = false;
        $this->mode = null;
        $this->lock = null;
        $this->queryLength = 0;
        $this->useChecksum = true;

        $this->connect(2);
        $this->updateState();
    }

    private function connect(int $retry = 0)
    {
        $this->socket->closeSocket();
        if (!$this->socket->openStream($this->ip, $this->port)) {
            if ($retry < 1) {
                return;
            }
            $this->connect(max($retry - 1, 0));
        }
    }

    private function updateState(int $retry = 2)
    {
        $rx = $this->queryState($retry);

        if (!$rx || count($rx) < $this->queryLength) {
            $this->isOn = false;
            return;
        }

        // typical response:
        // pos  0  1  2  3  4  5  6  7  8  9 10
        //    66 01 24 39 21 0a ff 00 00 01 99
        //     |  |  |  |  |  |  |  |  |  |  |
        //     |  |  |  |  |  |  |  |  |  |  checksum
        //     |  |  |  |  |  |  |  |  |  warmwhite
        //     |  |  |  |  |  |  |  |  blue
        //     |  |  |  |  |  |  |  green
        //     |  |  |  |  |  |  red
        //     |  |  |  |  |  speed: 0f = highest f0 is lowest
        //     |  |  |  |  <don't know yet>
        //     |  |  |  preset pattern
        //     |  |  off(23)/on(24)
        //     |  type
        //     msg head

        // response from a 5-channel LEDENET controller:
        // pos  0  1  2  3  4  5  6  7  8  9 10 11 12 13
        //    81 25 23 61 21 06 38 05 06 f9 01 00 0f 9d
        //     |  |  |  |  |  |  |  |  |  |  |  |  |  |
        //     |  |  |  |  |  |  |  |  |  |  |  |  |  checksum
        //     |  |  |  |  |  |  |  |  |  |  |  |  color mode (f0 colors were set, 0f whites, 00 all were set)
        //     |  |  |  |  |  |  |  |  |  |  |  cold-white
        //     |  |  |  |  |  |  |  |  |  |  <don't know yet>
        //     |  |  |  |  |  |  |  |  |  warmwhite
        //     |  |  |  |  |  |  |  |  blue
        //     |  |  |  |  |  |  |  green
        //     |  |  |  |  |  |  red
        //     |  |  |  |  |  speed: 0f = highest f0 is lowest
        //     |  |  |  |  <don't know yet>
        //     |  |  |  preset pattern
        //     |  |  off(23)/on(24)
        //     |  type
        //     msg head
        //

        // Devices that don't require a separate rgb/w bit
        if (
            $rx[1] === 0x04 ||
            $rx[1] === 0x33 ||
            $rx[1] === 0x81
        ) {
            $this->rgbwProtocol = true;
        }

        // Devices that actually support rgbw
        if (
            $rx[1] === 0x04 ||
            $rx[1] === 0x25 ||
            $rx[1] === 0x33 ||
            $rx[1] === 0x81
        ) {
            $this->rgbwCapable = true;
        }

        // Devices that use an 8-byte protocol
        if ($rx[1] === 0x25 ||
            $rx[1] === 0x27 ||
            $rx[1] === 0x35
        ) {
            $this->protocol = 'LEDENET';
        }

        if ($rx[1] === 0x01) {
            $this->protocol = 'LEDENET_ORIGINAL';
            $this->useChecksum = false;
        }

        $pattern = $rx[3];
        $wwLevel = $rx[9];
        $mode = $this->determineMode($wwLevel, $pattern);

        if ($mode === 'unknown') {
            if ($retry < 1) {
                return;
            }
            $this->updateState(max($retry - 1, 0));
            return;
        }

        $powerState = $rx[2];

        if ($powerState === 0x23) {
            $this->isOn = true;
        } elseif ($powerState === 0x24) {
            $this->isOn = false;
        }

        $this->rawState = $rx;
        $this->mode = $mode;
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

        if($rx === null || count($rx) < $this->queryLength) {
            if ($retry < 1) {
                $this->isOn = false;
                return $rx;
            }
            return $this->queryState(max($retry - 1, 0), $ledType);
        }

        return $rx;
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

    private function determineMode($warmWhiteLevel, $patternCode)
    {
        $mode = 'unknown';
        dump("ww_level " . $warmWhiteLevel);
        dump("pattern_level " . $patternCode);

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
}