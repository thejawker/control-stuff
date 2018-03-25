<?php

namespace TheJawker\ControlStuff\LedFlux;

use TheJawker\ControlStuff\Socket;

class BulbScanner
{
    /**
     * The message that gets broadcasted.
     *
     * @var string
     */
    const MESSAGE = 'HF-A11ASSISTHREAD';

    /**
     * A list of the Discovered lights.
     *
     * @var array
     */
    private $discoveredLights = [];

    /**
     * Starts scanning the network and discovering devices.
     *
     * @param int $timeout
     */
    public function scan(int $timeout = 1)
    {
        $socket = new Socket();
        $socket->timeout = $timeout;
        $socket->broadcast(self::MESSAGE, function ($data) {
            dump($data);
            $this->addLight($data);
        });

        dump($this->discoveredLights);
    }

    /**
     * Adds the Light to the list.
     *
     * @param $data
     */
    private function addLight($data)
    {
        [$ip, $id, $model] = $response = explode(',', $data, 3);

        if (count($response) < 3) {
            return;
        }

        $this->discoveredLights[$id] = [$ip, $id, $model];
    }
}